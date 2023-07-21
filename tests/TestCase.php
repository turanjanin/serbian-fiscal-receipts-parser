<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts\Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function loadTestFile(string $filename): string
    {
        return file_get_contents(__DIR__ . '/Fixtures/' . $filename);
    }
}
