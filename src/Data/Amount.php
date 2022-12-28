<?php

namespace Turanjanin\Receipts\Data;

class Amount
{
    public function __construct(
        public readonly int $wholeNumber,
        public readonly int $decimalNumber = 0,
    )
    {
    }
}
