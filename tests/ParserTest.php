<?php

namespace Turanjanin\FiscalReceipts\Tests;

use Turanjanin\FiscalReceipts\Data\Receipt;
use Turanjanin\FiscalReceipts\Data\ReceiptItem;
use Turanjanin\FiscalReceipts\Data\Store;
use Turanjanin\FiscalReceipts\Data\TaxItem;
use Turanjanin\FiscalReceipts\Parser;

class ParserTest extends TestCase
{
    /** @test */
    public function it_will_throw_an_exception_if_arbitrary_string_is_parsed()
    {
        $this->expectException(\RuntimeException::class);

        $parser = new Parser();
        $parser->parse('test');
    }

    /** @test */
    public function it_can_parse_a_receipt()
    {
        $receiptContent = $this->loadTestFile('1.txt');

        $parser = new Parser();

        $receipt = $parser->parse($receiptContent);
        $this->assertInstanceOf(Receipt::class, $receipt);

        $store = $receipt->store;
        $this->assertInstanceOf(Store::class, $store);
        $this->assertSame('MERCATOR-S', $store->companyName);
        $this->assertSame('101670560', $store->tin);
        $this->assertSame('1108934', $store->locationId);
        $this->assertSame('Roda Megamarket 345', $store->locationName);
        $this->assertSame('ВИЗАНТИЈСКИ БУЛЕВАР 1', $store->address);
        $this->assertSame('Ниш-Медијана', $store->city);

        $this->assertSame('746DUV64-746DUV64-16898', $receipt->number);
        $this->assertSame('16887/16898ПП', $receipt->counter);
        $this->assertCount(2, $receipt->meta);
        $this->assertSame('431/2.0.0.2', $receipt->meta['ЕСИР број']);

        $this->assertCount(5, $receipt->items);
        $firstItem = $receipt->items[0];
        $this->assertInstanceOf(ReceiptItem::class, $firstItem);

        $this->assertSame('BANANA', $firstItem->name);
        $this->assertSame(1.482, $firstItem->quantity);
        $this->assertSame('KG', $firstItem->unit);
        $this->assertSame('Е', $firstItem->tax->identifier);
        $this->assertSame(199_99, $firstItem->singleAmount->getParas());
        $this->assertSame(296_39, $firstItem->totalAmount->getParas());

        $fourthItem = $receipt->items[3];
        $this->assertSame('KESA VJZ 7KG 51 MIKRON', $fourthItem->name);
        $this->assertSame('KOM', $fourthItem->unit);
        $this->assertSame(1.0, $fourthItem->quantity);

        $this->assertCount(2, $receipt->taxes);
        $firstTax = $receipt->taxes[0];
        $this->assertInstanceOf(TaxItem::class, $firstTax);
        $this->assertSame('П-ПДВ', $firstTax->tax->name);
        $this->assertSame(10, $firstTax->tax->rate);
        $this->assertSame(74_19, $firstTax->amount->getParas());

        $this->assertSame(1000_00, $receipt->paymentSummary['Готовина']->getParas());

        $this->assertSame(829_12, $receipt->totalPurchaseAmount->getParas());
        $this->assertSame(76_36, $receipt->totalTaxAmount->getParas());

        $this->assertSame('2022-12-31 15:51:57', $receipt->date->format('Y-m-d H:i:s'));
        $this->assertTrue(strlen($receipt->qrCode) > 0);
    }

    /** @test */
    public function it_can_parse_receipts_where_item_name_spans_multiple_lines()
    {
        $receiptContent = $this->loadTestFile('2.txt');

        $parser = new Parser();
        $receipt = $parser->parse($receiptContent);

        $this->assertCount(2, $receipt->items);

        $this->assertSame('Torba MY BLUE BAG Š18xD70xV60 recikl.', $receipt->items[0]->name);
        $this->assertSame('KOM', $receipt->items[0]->unit);
        $this->assertSame(1_00, $receipt->items[0]->totalAmount->getParas());
        $this->assertSame('Ђ', $receipt->items[0]->tax->identifier);

        $this->assertSame('Stolnjak BLOMME 140x240 žuta', $receipt->items[1]->name);
        $this->assertSame('KOM', $receipt->items[1]->unit);
        $this->assertSame(2025_00, $receipt->items[1]->totalAmount->getParas());
        $this->assertSame('Ђ', $receipt->items[1]->tax->identifier);
    }

    /** @test */
    public function it_can_parse_non_fiscal_receipts()
    {
        $receiptContent = $this->loadTestFile('3.txt');

        $parser = new Parser();
        $receipt = $parser->parse($receiptContent);

        $this->assertCount(3, $receipt->items);
        $this->assertCount(1, $receipt->taxes);

        $this->assertSame(250_00, $receipt->totalTaxAmount->getParas());
        $this->assertSame(250_00, $receipt->totalTaxAmount->getParas());
    }

    /** @test */
    public function it_can_properly_parse_shorter_unit_identifiers()
    {
        $receiptContent = $this->loadTestFile('4.txt');

        $parser = new Parser();
        $receipt = $parser->parse($receiptContent);

        $this->assertCount(5, $receipt->items);

        $this->assertSame('0.5L AQUA VIVA VODA', $receipt->items[0]->name);
        $this->assertSame('FL', $receipt->items[0]->unit);

        $this->assertSame('18.5G NESCAFE CAPP.V', $receipt->items[1]->name);
        $this->assertSame('KO', $receipt->items[1]->unit);
    }

    /** @test */
    public function it_can_parse_unit_identifiers_glued_to_item_name()
    {
        $receiptContent = $this->loadTestFile('5.txt');

        $parser = new Parser();
        $receipt = $parser->parse($receiptContent);

        $this->assertSame('ESPRESSO', $receipt->items[0]->name);
        $this->assertSame('KOM', $receipt->items[0]->unit);

        $this->assertSame('CEDJENA NARANDZA 0.2', $receipt->items[2]->name);
        $this->assertSame('KOM', $receipt->items[2]->unit);
    }

    /** @test */
    public function it_can_parse_taxes_with_multiple_words_in_the_name()
    {
        $receiptContent = $this->loadTestFile('6.txt');

        $parser = new Parser();
        $receipt = $parser->parse($receiptContent);

        $this->assertCount(2, $receipt->taxes);
        $this->assertSame('О-ПДВ', $receipt->taxes[0]->tax->name);
        $this->assertSame('Без ПДВ', $receipt->taxes[1]->tax->name);
    }

    /** @test */
    public function it_can_parse_receipts_where_number_spans_multiple_lines()
    {
        $receiptContent = $this->loadTestFile('6.txt');

        $parser = new Parser();
        $receipt = $parser->parse($receiptContent);

        $this->assertSame('9VMEKMSN-DBV8GPO0-1599033', $receipt->number);
        $this->assertSame('1598723/1599033ПП', $receipt->counter);
    }

    /** @test */
    public function it_can_parse_receipts_where_unit_is_separated_by_backslash()
    {
        $receiptContent = $this->loadTestFile('7.txt');

        $parser = new Parser();
        $receipt = $parser->parse($receiptContent);

        $this->assertSame('BATERIJE', $receipt->items[0]->name);
        $this->assertSame('KO', $receipt->items[0]->unit);
    }
}
