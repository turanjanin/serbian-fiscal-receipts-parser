<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts\Tests\Data;

use Turanjanin\FiscalReceipts\Data\RsdAmount;
use Turanjanin\FiscalReceipts\Tests\TestCase;

class RsdAmountTest extends TestCase
{
    /** @test */
    public function it_can_return_float_representation_of_a_number()
    {
        $amount = new RsdAmount(141, 59);
        $this->assertSame(141.59, $amount->getFloat());

        $amount = new RsdAmount(215);
        $this->assertSame(215.0, $amount->getFloat());
    }

    /** @test */
    public function it_can_return_value_in_paras()
    {
        $amount = new RsdAmount(141, 59);
        $this->assertSame(14159, $amount->getParas());

        $amount = new RsdAmount(215);
        $this->assertSame(21500, $amount->getParas());
    }

    /** @test */
    public function it_can_create_new_instance_from_string()
    {
        $amount = RsdAmount::fromString('141,59');
        $this->assertSame(141, $amount->integer);
        $this->assertSame(59, $amount->fraction);

        $amount = RsdAmount::fromString('250');
        $this->assertSame(250, $amount->integer);
        $this->assertSame(0, $amount->fraction);

        $amount = RsdAmount::fromString('54.983,99');
        $this->assertSame(54983, $amount->integer);
        $this->assertSame(99, $amount->fraction);
    }

    /** @test */
    public function it_can_be_cast_to_string()
    {
        $amount = new RsdAmount(141, 59);
        $this->assertSame('141,59', (string)$amount);

        $amount = new RsdAmount(215);
        $this->assertSame('215,00', (string)$amount);

        $amount = new RsdAmount(54983, 99);
        $this->assertSame('54.983,99', (string)$amount);
    }
}
