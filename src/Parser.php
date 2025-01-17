<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts;

use DateTimeImmutable;
use DateTimeZone;
use Masterminds\HTML5;
use QueryPath\QueryPath;
use Turanjanin\FiscalReceipts\Data\ReceiptType;
use Turanjanin\FiscalReceipts\Data\RsdAmount;
use Turanjanin\FiscalReceipts\Data\Receipt;
use Turanjanin\FiscalReceipts\Data\ReceiptItem;
use Turanjanin\FiscalReceipts\Data\Store;
use Turanjanin\FiscalReceipts\Data\Tax;
use Turanjanin\FiscalReceipts\Data\TaxItem;
use Turanjanin\FiscalReceipts\Exceptions\ParsingException;

class Parser
{
    public function parseApiResponse(array $data): Receipt
    {
        if (!array_key_exists('invoiceRequest', $data) || !array_key_exists('invoiceResult', $data) || !array_key_exists('journal', $data)) {
            throw new ParsingException('The data should represent valid API response.');
        }

        [$locationId, $locationName] = explode('-', $data['invoiceRequest']['locationName'], 2);

        $store = new Store(
            companyName: trim($data['invoiceRequest']['businessName']),
            tin: trim($data['invoiceRequest']['taxId']),
            locationId: trim($locationId),
            locationName: trim($locationName),
            address: trim($data['invoiceRequest']['address']),
            city: trim($data['invoiceRequest']['administrativeUnit']),
        );

        $journal = str_replace("\r\n", "\n", $data['journal']);
        $parsedJournal = $this->parseJournal($journal);

        $date = (new DateTimeImmutable($data['invoiceResult']['sdcTime']))->setTimezone(new DateTimeZone('Europe/Belgrade'));
        $type = ReceiptType::get($data['invoiceRequest']['invoiceType'], $data['invoiceRequest']['transactionType']);

        /**
         * Transaction types:
         *  - 0: Sale
         *  - 1: Refund
         */
        if ($data['invoiceRequest']['transactionType'] === 0) {
            $totalPurchaseAmount = RsdAmount::fromFloat($data['invoiceResult']['totalAmount']);
            $totalRefundAmount = new RsdAmount(0);
        } else {
            $totalPurchaseAmount = new RsdAmount(0);
            $totalRefundAmount = RsdAmount::fromFloat($data['invoiceResult']['totalAmount']);
        }

        return new Receipt(
            store: $store,
            number: $data['invoiceResult']['invoiceNumber'],
            counter: $data['invoiceResult']['transactionTypeCounter'] . '/' . $data['invoiceResult']['totalCounter'] . $data['invoiceResult']['invoiceCounterExtension'],
            type: $type,
            meta: $parsedJournal->meta,
            items: $parsedJournal->items,
            taxItems: $parsedJournal->taxItems,
            paymentSummary: $parsedJournal->paymentSummary,
            totalPurchaseAmount: $totalPurchaseAmount,
            totalRefundAmount: $totalRefundAmount,
            totalTaxAmount: $parsedJournal->totalTaxAmount,
            date: $date,
            qrCode: $parsedJournal->qrCode,
        );
    }

    public function parseJournal(string $receiptContent): Receipt
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
            throw new ParsingException('The receipt content should be a string that spans multiple lines.');
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
            throw new ParsingException('Receipt structure not found in the given string.');
        }

        $header = mb_substr($sections[0], 0, mb_strrpos($sections[0], "\n--"));

        $store = $this->extractStoreData($header);
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
            $source = (new HTML5)->loadHTML($qrCodeData);
            $qrCode = QueryPath::withHTML5($source)->find('img')->attr('src') ?? '';
        }


        $body = $sections[1];
        [$itemData, $paymentData] = explode($subDelimiter, $body, 2);

        $items = $this->extractItemData($itemData, $taxTypes);
        $paymentSummary = array_map([RsdAmount::class, 'fromString'], $this->extractKeyValuePairs($paymentData));

        $counter = $fiscalization['Бројач рачуна'] ?? '';
        $typeIndicator = mb_substr($counter, -2);
        $type = ReceiptType::tryFrom($typeIndicator) ?? ReceiptType::NormalSale;

        return new Receipt(
            store: $store,
            number: $fiscalization['ПФР број рачуна'] ?? '',
            counter: $counter,
            type: $type,
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

        $tin = trim($lines[0]);
        $companyNameLines = [];
        $locationId = '';
        $locationName = '';
        $address = '';
        $city = '';

        // Company name can span multiple line, until we reach the location id / name pair.
        for ($i = 1, $count = count($lines); $i < $count; $i++) {
            $line = trim($lines[$i]);

            if (preg_match("/([0-9]+)\-(.+)/ui", $line, $matches)) {
                $locationId = $matches[1];
                $locationName = trim($matches[2]);
                break;
            }

            $companyNameLines[] = $line;
        }

        /**
         * TODO: Can we process the following cases?
         *  - Location name can span multiple lines:
         *      - https://suf.purs.gov.rs/v/?vl=A05DRjY1UlhZTkNGNjVSWFmCUAAAgVAAAKBaMgAAAAAAAAABilcx7KIAAAAjhTToDyIlpfRcGCKbFB4GYM4dXAye5cGXJMhPbsqjCGq7rZCYRxyEO13T5va%2BTtrcOmBKhjJSeny9zk%2B9LQl0otzl6BcvisYdHDlvKehwZdTYZeOeLxCvdeY1nd%2Fxt6qxskz3Ngz2n%2FVdEH2GclA2xjXwsZTsMew2ZbFR2eyW0RwJN%2BKZODBeZZIv4%2FmN3JrSnu2Cb6FDl2%2F3X67vYcKS1BL%2BIPVk3UZKEolStSuL6zMfNA1Y2UN9nsl4whbguUGV%2FLWjWwDUiDkLOnKrQdKw8FC4FNuMHG8Yx3u9Kafuc2ImXuV5gYHM%2Fxd%2BLa5WK2EWf90Fev33pXvBrh3MAXGkXUXqHxqESTcJXaxr9XEfMTnoNDJvPoordMSPVx1fVRDefxYlgg21Z9g9e1F6KhKj%2BhZ1xbHWT%2FYTPfT40Ru4N3a0lUS4tAiuluM3iDAYW5rETa17Zso5JhZspNtyYbmHdsb0Nl3YE9bkgnZXSegRpuwtvEz7KwgTaDHHhCjR%2FDprkaacdvWa8mbHBONGaRyRWY9x4g%2FS8FOYIOZMzkksy0E2r9fJI3a8bHMHxCC3yX%2BHMtVXFwvYxAgZq0jFZpKD20CPedrctLPJt48iSQP8s7JJrqdZa8HVDwJohHL%2FwiEvCSLV2DC%2BP6An89Fyxeo2EoXdbGKkfQxYmb2CGTAsPgUE1lYmcKvzQX5WppSlgVM%3D
         *      - https://suf.purs.gov.rs/v/?vl=A0pKNFVZTVJGSko0VVlNUkZzGwAAZRsAAOB5rwAAAAAAAAABjU%2BB5%2FEAAABpTy7unuLhcXg9ZfaKVXgL8Qy6rRNpuxzEveQd67GfOzZgHgA%2BJIK42JzQB7ubdMAnu%2BL3JNwm7642BTWrLRJ1JTNhGt3t7lkBLKYeHK9jMXyo79svwUnMGU9LAO6G8GGrWrrwtVZf7GykiQd5SJiMJi%2FKBGwTZUZJU3DVKExIL5SwyXIsXsjdcy%2B8avj9ODZiAmwhZuROOgV54as09mxspbmSM5CG4nl0qrgZKeSJ6hDZFyvxTOdNnvt6OMDy06KYBKopQ5Y5c5CY3ueEY924mZx2vzRrK5gq1EoQ9PsHqu95jwYoNyVaILth%2FS%2FcJxdQgG0ECRwbofaKDSFEXV8Tc4045jW9%2BYeqmDIb0G1jowHIwLBwbJN12A2KPbxP8ZqBoNNfhKJJCeimt3SPA7cnwKMpmAB1fUEpP6izdxc5iJ%2F1ebn8ZkXZ7Gfc29TwadvvUZVGN1Ie0FkCAVA5b%2BCa1vR1E9CHQWTaHI2L9eZ30JOI13w5pdxm1LDIekTVenQ9jrsW6xx5gWcWHCvbKd0jyTf40Hp7a06Qj4pnZNe75XTjIW4um4TKtmdHbRaMAcv7ljNokh4v6KF0P3M%2BAG1fYEuTi33VuZO6jV9ySlu7PE3o1Byw9hdjzLhU%2F6VWWn4dNt16Ij9%2FJ03ZMAeIIzbDbwrGcsF2rZl20XR%2FUdLzZ4grsH0e%2F8eXsXKA1dCHmn4%3D
         *  - Address can span multiple lines:
         *      - https://suf.purs.gov.rs/v/?vl=A0pGTjJKNkpRSkZOMko2SlEUxwAASMYAABDrCQAAAAAAAAABhxkvlzYAAABd3dUWrzBwp%2FJVhDymOF%2BP8BLruog1GhT3hLglupnFRYet58ZKSrMvdK8DVsMr7D4AhIPX0FPcYcFX4QWWjRNenpNmlgg9vo7ABJH0Lf8R2nsKY4IJOF%2FWIbzV9E3f6%2FNUtARTSGBy5QDERUhhCD9DJOmg%2Bz%2F9cYjYm3jN8ctNA5bmHBXw3g%2Fk81JhZ21FZ2AyGoOZ3zx1Tzc8SbFgK0lTp77drLw5cek5l1QepGU7VIxaGzwpvTlU4BDe3orKlMmL9XTqFQvIBq7rgmodU8Loy3JseN7VopjDnn8ALR9PTAnTvvfZwdE6Wr%2BpsIWvom2ZN7JP2f2GT6vBjfCAJytQhL1WN3d2kE5QO4C8iIDJt4TRZDsJkic77M8xbW%2BHCEdtet%2BBpQ0F7T4DGf0BP3VcjiN%2BMRKDBmt7oIHfj%2FyZYoCuRcgL7r7PW7z%2FB1OW403hfTp%2Fvn7MLBmrqJt1vmXtsMycDI3XJBVeIJJRj%2BXlenaL6Ug249avQhahkZYqKzmj%2BjkprJj%2BQLOBsnmdaRyR1rK9fT71hnmIASwTOb6UOr0CsRPUiK90vbY8Y3GfFn1NvL9VVI9nCZ7quJezSupZvWh7Y75RxRywxtjLrWnwHkZqZro%2B%2FF6XEDiX5c109Zu4WlDkf72NLtNddmMLpCifc2kLNU6VoKSSyiYW4HvJBLJU9Pqsja1Yrx5BWljAyYU%3D
         *      - https://suf.purs.gov.rs/v/?vl=A1NETkRZSlJRU0RORFlKUlHdNgEAuCwBAIAjQwAAAAAAAAABkBGcVygAAAA4WIjKrFOQOxVp9zqPGN4aQJzNRrS2OpKb5w+7nuXOBLQviaIqM/uTR0x9Vtyfqa5wcKuuS/quTuEjUg2TG7jg2gBaZJxOf7zftOi/zUvD2W9jceszx+NepNi3DoVBV4+h+xbPJcr5mKh2xjaPdzwxoEWUPn+vf+DPVxWJekz0H2yPH+Z9f2jTcq6RNjU1YfyXQr8NMnZM7MFNMzSCZmEeTOFH3Oi1sPT80xRBc4Yik7/tjbO9mskvOFHBXuwmcFFBEj9ktVSgKB4AldH1G0pX29i/PP2emhKSLEpy9Hk6ympk3ljpW4MaZMr2jjKdj9YjuCd0yrWbu/QlsFY9fPViOI6El2FZaMB9H+CuL4lcNlYtVKnZG3mcBVd6P5XRSliyfCMQSGE4Qaz5s3puIbyzUQTX9tAOU6H5rsew/j78gwr5pt5nN1Kz9KlXjNqC7rwT4jkKhtkEW9uk5qRCLXg9vrWr9hC3XcqW04p6zZTltikhLKUBhIgI857ritkvGBXIIciYv+lYJtqRBdiDSYSkCdfw1XE832dSaC7BGsANGYunXaNexFCPU75XVJRNgrTnOS2Z37gXNhWglN+BzCG8pOnX/Smpx9yrT0dt3VV2I2PIhYdADXArktgROXCyX1FCOKiyFdupH2mi+Qc1YiquFPVSPpm0ESXUH3CFTa20CbVkpScThXoeTBr0oT24u/0=
         */

        if (isset($lines[++$i])) {
            $address = trim($lines[$i]);
        }

        if (isset($lines[++$i])) {
            $city = trim($lines[$i]);
        }

        return new Store(
            companyName: implode(' ', $companyNameLines),
            tin: $tin,
            locationId: $locationId,
            locationName: $locationName,
            address: $address,
            city: $city,
        );
    }

    /**
     * @param array<string, Tax> $taxes
     */
    private function extractItemData(string $itemData, array $taxes): array
    {
        $lines = explode("\n", trim($itemData));

        $identifiers = array_keys($taxes);

        $itemCodePrefix = '(?:\[?[0-9]{3,}\]?(?: |,|\-))';
        $itemCodeSuffix = '(?:(?: |,|\-| \- |\/|#)[0-9]{3,})';
        $itemName = '(?<name>.*?)';
        $unit = '(?: \/ |\/ | \/|\/| \\\ | \\\|\\\ |\\\| +|\(|\[)(\(|\[)?(?<unit>kom|kom\.|kg|kgr|l|lit|lit\.|kut|por|m|pce|ko|fl|ком|ком\.|кг|кгр|л|кут|пор|м|ко|фл)(\)|\])?';
        $taxIdentifier = ' ?\((?<taxIdentifier>' . implode('|', $identifiers) . ')\)';

        $items = [];
        $itemLine = '';
        for ($i = 1, $count = count($lines); $i < $count; $i++) {
            // Description of item can span multiple lines. It's safer to test for "amount line" first.
            preg_match("/([0-9,.]+)\s+([0-9,.]+)\s+(-?[0-9,.]+)$/", $lines[$i], $amountMatches);

            if (empty($amountMatches)) {
                $itemLine .= $lines[$i];
                continue;
            }

            $itemLine = trim($itemLine);

            $lineVariants = [
                "{$itemCodePrefix}{$itemName}{$unit}{$taxIdentifier}",
                "{$itemName}{$itemCodeSuffix}{$unit}{$taxIdentifier}",
                "{$itemName}{$unit}{$itemCodeSuffix}{$taxIdentifier}",
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
                    $pairs[$lastKey] .= trim($line);
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
        $integer = str_replace('.', '', $integer);
        $fraction ??= 0;

        return floatval("{$integer}.{$fraction}");
    }
}
