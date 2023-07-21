<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts\Data;

class Tax
{
    public function __construct(
        public readonly string $name,
        public readonly string $identifier,
        public readonly int $rate,
    )
    {
    }
}
