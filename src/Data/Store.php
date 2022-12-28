<?php

namespace Turanjanin\Receipts\Data;

class Store
{
    public function __construct(
        public readonly string $companyName,
        public readonly string $tin,
        public readonly string $locationName,
        public readonly string $address,
        public readonly string $city,
        public readonly string $administrativeUnit,
    )
    {
    }
}
