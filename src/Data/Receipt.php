<?php

namespace Turanjanin\Receipts\Data;

use DateTimeImmutable;

class Receipt
{
    public function __construct(
        public readonly Store $store,
        public readonly string $number,
        public readonly string $counter,
        /** @var ReceiptItem[] */
        public readonly array $items,
        public readonly Amount $totalPurchaseAmount,
        public readonly Amount $totalTaxAmount,
        public readonly DateTimeImmutable $date,
    )
    {
    }
}
