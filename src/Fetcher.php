<?php

declare(strict_types=1);

namespace Turanjanin\Receipts;

use DateTimeImmutable;
use DateTimeZone;
use QueryPath\QueryPath;
use Turanjanin\Receipts\Data\RsdAmount;
use Turanjanin\Receipts\Data\Receipt;
use Turanjanin\Receipts\Data\ReceiptItem;
use Turanjanin\Receipts\Data\Store;
use Turanjanin\Receipts\Data\Tax;
use Turanjanin\Receipts\Data\TaxItem;

class Fetcher
{
    public function get(string $url): Receipt
    {
        $pageContent = $this->getContent($url);
        $receiptContent = $this->getReceiptContent($pageContent);

        return $this->parseReceipt($receiptContent);
    }

    private function getContent(string $url): string
    {
        return file_get_contents($url);
    }

    public function getReceiptContent(string $html): string
    {
        $document = QueryPath::withHTML5($html);
        $receiptContent = $document->find('pre')->first()->innerHTML() ?? '';

        if (empty($receiptContent)) {
            throw new \RuntimeException('Receipt data not found.');
        }

        return $receiptContent;
    }

    public function parseReceipt(string $receiptContent): Receipt
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
        $receiptContent = mb_substr($receiptContent, mb_strpos($receiptContent, "\n") + 1);
        $receiptContent = mb_substr($receiptContent, 0, mb_strrpos($receiptContent, "\n"));

        $sectionDelimiter = '========================================';
        $subDelimiter = '----------------------------------------';

        [$header, $body, $taxData, $fiscalizationData, $qrCodeData] = explode($sectionDelimiter, $receiptContent);

        // Lines 0 - 4 are always displaying store data.
        $store = $this->extractStoreData($header);

        // Lines 5+ have various additional meta-data
        $meta = $this->extractKeyValuePairs($header);


        $lines = explode("\n", trim($taxData));
        $taxes = [];
        $taxTypes = [];
        $totalTaxAmount = new RsdAmount(0);
        for ($i = 1, $count = count($lines); $i <= $count; $i++) {
            if ($lines[$i] == $subDelimiter) {
                $totals = $this->extractKeyValuePairs($lines[$i + 1] ?? '');
                $totalTaxAmount = RsdAmount::fromString($totals['Укупан износ пореза'] ?? '');
                break;
            }

            $parts = preg_split('/ +/', $lines[$i]);

            $identifier = $parts[0];

            $tax = new Tax(
                name: $parts[1],
                identifier: $identifier,
                rate: intval($parts[2]),
            );

            $taxTypes[$identifier] = $tax;

            $taxes[] = new TaxItem(
                tax: $tax,
                amount: RsdAmount::fromString($parts[3]),
            );
        }


        [$itemData, $paymentData] = explode($subDelimiter, $body, 2);

        $items = $this->extractItemData($itemData, $taxTypes);
        $paymentSummary = array_map([RsdAmount::class, 'fromString'], $this->extractKeyValuePairs($paymentData));

        $fiscalization = $this->extractKeyValuePairs($fiscalizationData);

        $timezone = new DateTimeZone('Europe/Belgrade');
        $date = DateTimeImmutable::createFromFormat('d.m.Y. H:i:s', $fiscalization['ПФР време'] ?? '', $timezone);
        if ($date === false) {
            $date = new DateTimeImmutable('now', $timezone);
        }

        $qrCode = QueryPath::withHTML5($qrCodeData)->find('img')->attr('src') ?? '';

        return new Receipt(
            store: $store,
            number: $fiscalization['ПФР број рачуна'] ?? '',
            counter: $fiscalization['Бројач рачуна'] ?? '',
            meta: $meta,
            items: $items,
            taxes: $taxes,
            paymentSummary: $paymentSummary,
            totalPurchaseAmount: $paymentSummary['Укупан износ'] ?? new RsdAmount(0),
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

    private function extractTaxData(string $content): array
    {
        $lines = explode("\n", trim($content));

        $delimiter = '----------------------------------------';

        $taxes = [];
        for ($i = 1, $count = count($lines); $i <= $count; $i++) {
            if ($lines[$i] == $delimiter) {
                break;
            }

            $parts = preg_split('/ +/', $lines[$i]);

            $taxes[$parts[0]] = new Tax(
                name: $parts[1],
                identifier: $parts[0],
                rate: intval($parts[2]),
            );
        }

        return $taxes;
    }

    /**
     * @param array<string, Tax> $taxes
     */
    private function extractItemData(string $itemData, array $taxes): array
    {
        $lines = explode("\n", trim($itemData));

        $optionalPrefixItemCode = '(?:[0-9]{3,}(?: |,|\-))?';
        $optionalSuffixItemCode = '(?:(?: |,|\-)[0-9]{3,})?';
        $itemName = '(?<name>.*)';
        $unit = '(?:\/|\/ | )(?<unit>kom|kg|l|lit|kut|m|pce|ko|fl)';
        $taxIdentifier = '\((?<taxIdentifier>е|ђ|a)\)';

        $items = [];
        $itemLine = '';
        for ($i = 1, $count = count($lines); $i < $count; $i++) {
            // Description of item can span multiple lines. It's safer to test for "amount line" first.
            preg_match("/([0-9,.]+)\s+([0-9,.]+)\s+([0-9,.]+)/", $lines[$i], $amountMatches);

            if (empty($amountMatches)) {
                $itemLine .= $lines[$i];
                continue;
            }

            $itemLine = trim($itemLine);

            $lineVariants = [
                "{$optionalPrefixItemCode}{$itemName}{$unit} {$taxIdentifier}",
                "{$itemName}{$optionalSuffixItemCode}{$unit} {$taxIdentifier}",
                "{$optionalPrefixItemCode}{$itemName} {$taxIdentifier}",
                "{$itemName}{$optionalSuffixItemCode} {$taxIdentifier}",
            ];

            foreach ($lineVariants as $variant) {
                preg_match("/$variant/ui", $itemLine, $lineMatches);

                if (!empty($lineMatches)) {
                    break;
                }
            }

            $tax = $taxes[$lineMatches['taxIdentifier']];
            $totalAmount = RsdAmount::fromString($amountMatches[3] ?? '0');

            $items[] = new ReceiptItem(
                name: trim($lineMatches['name']),
                quantity: floatval($amountMatches[2] ?? '0'),
                unit: trim($lineMatches['unit'] ?? 'KOM'),
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
        foreach ($lines as $line) {
            $parts = preg_split('/: /', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $pairs[trim($parts[0])] = trim($parts[1]);
        }

        return $pairs;
    }
}
