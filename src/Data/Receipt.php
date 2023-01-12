<?php

declare(strict_types=1);

namespace Turanjanin\Receipts\Data;

use DateTimeImmutable;

class Receipt
{
    public function __construct(
        public readonly Store $store,
        public readonly string $number,
        public readonly string $counter,
        /** @var array<string, string> */
        public readonly array $meta,
        /** @var ReceiptItem[] */
        public readonly array $items,
        /** @var TaxItem[] */
        public readonly array $taxes,
        /** @var array<string, RsdAmount> */
        public readonly array $paymentSummary,
        public readonly RsdAmount $totalPurchaseAmount,
        public readonly RsdAmount $totalTaxAmount,
        public readonly DateTimeImmutable $date,
        public string $qrCode,
    )
    {
    }
}
