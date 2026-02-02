<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts\Data;

class RsdAmount
{
    public function __construct(
        public readonly int $integer,
        public readonly int $fraction = 0,
    )
    {
    }

    public function getFloat(): float
    {
        return $this->integer + $this->fraction / 100;
    }

    public function getParas(): int
    {
        return $this->integer * 100 + $this->fraction;
    }

    public static function fromString(string $string): self
    {
        @[$integer, $fraction] = explode(',', $string, 2);

        $integer = intval(str_replace('.', '', $integer));
        $fraction = intval($fraction);

        return new self($integer, $fraction);
    }

    public static function fromFloat(float $value): self
    {
        $rounded = round($value, 2);
        $totalParas = (int)round($rounded * 100);

        $integer = intdiv($totalParas, 100);
        $fraction = abs($totalParas % 100);

        return new self($integer, $fraction);
    }

    public function __toString(): string
    {
        return number_format($this->getFloat(), 2, ',', '.');
    }
}
