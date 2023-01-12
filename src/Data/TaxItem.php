<?php

declare(strict_types=1);

namespace Turanjanin\Receipts\Data;
class TaxItem
{
    public function __construct(
        public readonly Tax $tax,
        public readonly RsdAmount $amount,
    )
    {
    }
}
