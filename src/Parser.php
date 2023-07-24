<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts;

use DateTimeImmutable;
use DateTimeZone;
use QueryPath\QueryPath;
use Turanjanin\FiscalReceipts\Data\RsdAmount;
use Turanjanin\FiscalReceipts\Data\Receipt;
use Turanjanin\FiscalReceipts\Data\ReceiptItem;
use Turanjanin\FiscalReceipts\Data\Store;
use Turanjanin\FiscalReceipts\Data\Tax;
use Turanjanin\FiscalReceipts\Data\TaxItem;

class Parser
{
    public function parse(string $receiptContent): Receipt
    {
        /**
         * Every receipt is shown within a pair of delimiters. These can differ based on the receipt type.
         * Here are some examples:
         *   ============ ФИСКАЛНИ РАЧУН ============
         *   ======== КРАЈ ФИСКАЛНОГ РАЧУНА =========
         *
         *   ======== ОВО НИЈЕ ФИСКАЛНИ РАЧУН =======
         *   ======== ОВО НИЈЕ ФИСКАЛНИ РАЧУН =======
         *
         * Therefore, we will try to remove first and last line.
         */

        if (mb_strpos($receiptContent, "\n") === false) {
            throw new \RuntimeException('The receipt content should be a string that spans multiple lines.');
        }

        $receiptContent = mb_substr($receiptContent, mb_strpos($receiptContent, "\n") + 1);
        $receiptContent = mb_substr($receiptContent, 0, mb_strrpos($receiptContent, "\n"));

        $sectionDelimiter = '========================================';
        $subDelimiter = '----------------------------------------';

        $sections = explode($sectionDelimiter, $receiptContent);

        /**
         * Receipt should typically have the following sections:
         *  - 0: Header
         *  - 1: List of items in the receipt
         *  - 2: List of different taxes applied to receipt items
         *  - 3: Fiscalization data
         *  - 4: QR code image
         */

        if (count($sections) < 5) {
            throw new \RuntimeException('Receipt structure not found in the given string.');
        }

        $header = mb_substr($sections[0], 0, mb_strrpos($sections[0], "\n--"));

        // Lines 0 - 4 are always displaying store data.
        $store = $this->extractStoreData($header);

        // Lines 5+ have various additional meta-data
        $meta = $this->extractKeyValuePairs($header);


        $sectionKey = 2;
        // There can be an extra section in the middle saying "ОВО НИЈЕ ФИСКАЛНИ РАЧУН"
        if (!str_starts_with(trim($sections[$sectionKey]), 'Ознака')) {
            $sectionKey++;
        }

        $taxData = $sections[$sectionKey];

        $lines = explode("\n", trim($taxData));
        $taxItems = [];
        $taxTypes = [];
        $totalTaxAmount = new RsdAmount(0);
        for ($i = 1, $count = count($lines); $i <= $count; $i++) {
            if ($lines[$i] == $subDelimiter) {
                $totals = $this->extractKeyValuePairs($lines[$i + 1] ?? '');
                $totalTaxAmount = RsdAmount::fromString($totals['Укупан износ пореза'] ?? '');
                break;
            }

            $parts = preg_split('/ {2,}/', $lines[$i], 4);
            $identifier = $parts[0];

            $tax = new Tax(
                name: $parts[1],
                identifier: $identifier,
                rate: intval($parts[2]),
            );

            $taxTypes[$identifier] = $tax;

            $taxItems[] = new TaxItem(
                tax: $tax,
                amount: RsdAmount::fromString($parts[3]),
            );
        }

        $fiscalizationData = $sections[++$sectionKey];
        $fiscalization = $this->extractKeyValuePairs($fiscalizationData);

        $timezone = new DateTimeZone('Europe/Belgrade');
        $date = DateTimeImmutable::createFromFormat('d.m.Y. H:i:s', $fiscalization['ПФР време'] ?? '', $timezone);
        if ($date === false) {
            $date = new DateTimeImmutable('now', $timezone);
        }

        $qrCodeData = $sections[++$sectionKey];
        $qrCode = '';
        if (trim($qrCodeData) !== '') {
            $qrCode = QueryPath::withHTML5($qrCodeData)->find('img')->attr('src') ?? '';
        }


        $body = $sections[1];
        [$itemData, $paymentData] = explode($subDelimiter, $body, 2);

        $items = $this->extractItemData($itemData, $taxTypes);
        $paymentSummary = array_map([RsdAmount::class, 'fromString'], $this->extractKeyValuePairs($paymentData));

        return new Receipt(
            store: $store,
            number: $fiscalization['ПФР број рачуна'] ?? '',
            counter: $fiscalization['Бројач рачуна'] ?? '',
            meta: $meta,
            items: $items,
            taxItems: $taxItems,
            paymentSummary: $paymentSummary,
            totalPurchaseAmount: $paymentSummary['Укупан износ'] ?? new RsdAmount(0),
            totalRefundAmount: $paymentSummary['Укупна рефундација'] ?? new RsdAmount(0),
            totalTaxAmount: $totalTaxAmount,
            date: $date,
            qrCode: mb_substr($qrCode, mb_strlen('data:image/gif;base64,')),
        );
    }

    private function extractStoreData(string $content): Store
    {
        $lines = explode("\n", $content);
        [$locationId, $locationName] = explode('-', $lines[2], 2);

        return new Store(
            companyName: trim($lines[1]),
            tin: trim($lines[0]),
            locationId: trim($locationId),
            locationName: trim($locationName),
            address: trim($lines[3]),
            city: trim($lines[4]),
        );
    }

    /**
     * @param array<string, Tax> $taxes
     */
    private function extractItemData(string $itemData, array $taxes): array
    {
        $lines = explode("\n", trim($itemData));

        $identifiers = array_keys($taxes);

        $itemCodePrefix = '(?:[0-9]{3,}(?: |,|\-))';
        $itemCodeSuffix = '(?:(?: |,|\-)[0-9]{3,})';
        $itemName = '(?<name>.*)';
        $unit = '(?:\/|\/ | \/| |\\\)\(?(?<unit>kom|kg|l|lit|lit\.|kut|m|pce|ko|fl|ком|кг|л|кут|м|ко|фл)\)?';
        $taxIdentifier = ' ?\((?<taxIdentifier>' . implode('|', $identifiers) . ')\)';

        $items = [];
        $itemLine = '';
        for ($i = 1, $count = count($lines); $i < $count; $i++) {
            // Description of item can span multiple lines. It's safer to test for "amount line" first.
            preg_match("/([0-9,.]+)\s+([0-9,.]+)\s+(-?[0-9,.]+)/", $lines[$i], $amountMatches);

            if (empty($amountMatches)) {
                $itemLine .= $lines[$i];
                continue;
            }

            $itemLine = trim($itemLine);

            $lineVariants = [
                "{$itemCodePrefix}{$itemName}{$unit}{$taxIdentifier}",
                "{$itemName}{$itemCodeSuffix}{$unit}{$taxIdentifier}",
                "{$itemName}{$unit}{$taxIdentifier}",

                "{$itemCodePrefix}{$itemName}{$taxIdentifier}",
                "{$itemName}{$itemCodeSuffix}{$taxIdentifier}",
                "{$itemName}{$taxIdentifier}",
            ];

            foreach ($lineVariants as $variant) {
                preg_match("/^{$variant}$/ui", $itemLine, $lineMatches);

                if (!empty($lineMatches)) {
                    break;
                }
            }

            $tax = $taxes[$lineMatches['taxIdentifier']];
            $totalAmount = RsdAmount::fromString($amountMatches[3] ?? '0');

            $items[] = new ReceiptItem(
                name: trim($lineMatches['name']),
                quantity: $this->convertDecimalCommaToPoint($amountMatches[2] ?? '0'),
                unit: mb_strtoupper(trim($lineMatches['unit'] ?? 'KOM')),
                tax: $tax,
                singleAmount: RsdAmount::fromString($amountMatches[1] ?? '0'),
                totalAmount: $totalAmount,
            );

            $itemLine = '';
        }

        return $items;
    }

    private function extractKeyValuePairs(string $content): array
    {
        $lines = explode("\n", $content);

        $pairs = [];
        $lastKey = null;
        foreach ($lines as $line) {
            $parts = preg_split('/: /', $line, 2);

            if (count($parts) !== 2) {
                if ($lastKey) {
                    $pairs[$lastKey] .= $line;
                }
                continue;
            }

            $lastKey = trim($parts[0]);
            $pairs[$lastKey] = trim($parts[1]);
        }

        return $pairs;
    }

    private function convertDecimalCommaToPoint(string $input): float
    {
        @[$integer, $fraction] = explode(',', $input, 2);

        $integer = intval(str_replace('.', '', $integer));
        $fraction = intval($fraction);

        return floatval("{$integer}.{$fraction}");
    }
}
