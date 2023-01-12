<?php

declare(strict_types=1);

namespace Turanjanin\Receipts\Data;

class Store
{
    public function __construct(
        public readonly string $companyName,
        public readonly string $tin,
        public readonly string $locationId,
        public readonly string $locationName,
        public readonly string $address,
        public readonly string $city,
    )
    {
    }
}
