<?php

namespace Turanjanin\Receipts\Data;

class ReceiptItem
{
    public function __construct(
        public readonly string $name,
        public readonly float $quantity,
        public readonly string $unit,
        public readonly int $taxRate,
        public readonly Amount $singleAmount,
        public readonly Amount $taxAmount,
        public readonly Amount $totalAmount,
    )
    {
    }
}
