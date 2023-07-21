<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts\Data;

class ReceiptItem
{
    public function __construct(
        public readonly string $name,
        public readonly float $quantity,
        public readonly string $unit,
        public readonly Tax $tax,
        public readonly RsdAmount $singleAmount,
        public readonly RsdAmount $totalAmount,
    )
    {
    }
}
