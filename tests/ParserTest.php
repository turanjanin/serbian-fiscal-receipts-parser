<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts\Tests;

use Turanjanin\FiscalReceipts\Data\Receipt;
use Turanjanin\FiscalReceipts\Data\ReceiptItem;
use Turanjanin\FiscalReceipts\Data\ReceiptType;
use Turanjanin\FiscalReceipts\Data\Store;
use Turanjanin\FiscalReceipts\Data\TaxItem;
use Turanjanin\FiscalReceipts\Exceptions\ParsingException;
use Turanjanin\FiscalReceipts\Parser;

class ParserTest extends TestCase
{
    /** @test */
    public function it_will_throw_an_exception_if_arbitrary_string_is_parsed()
    {
        $this->expectException(ParsingException::class);

        $parser = new Parser();
        $parser->parseJournal('test');
    }

    /** @test */
    public function it_can_parse_a_normal_sale_receipt()
    {
        $receiptContent = $this->loadTestFile('1.txt');

        $parser = new Parser();

        $receipt = $parser->parseJournal($receiptContent);
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
        $this->assertSame(ReceiptType::NormalSale, $receipt->type);
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

        $this->assertCount(2, $receipt->taxItems);
        $firstTax = $receipt->taxItems[0];
        $this->assertInstanceOf(TaxItem::class, $firstTax);
        $this->assertSame('П-ПДВ', $firstTax->tax->name);
        $this->assertSame(10, $firstTax->tax->rate);
        $this->assertSame(74_19, $firstTax->amount->getParas());

        $this->assertSame(1000_00, $receipt->paymentSummary['Готовина']->getParas());

        $this->assertSame(829_12, $receipt->totalPurchaseAmount->getParas());
        $this->assertSame(0, $receipt->totalRefundAmount->getParas());
        $this->assertSame(76_36, $receipt->totalTaxAmount->getParas());

        $this->assertSame('2022-12-31 15:51:57', $receipt->date->format('Y-m-d H:i:s'));
        $this->assertTrue(strlen($receipt->qrCode) > 0);
    }

    /** @test */
    public function it_can_parse_receipts_where_item_name_spans_multiple_lines()
    {
        $receiptContent = $this->loadTestFile('2.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

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
    public function it_can_parse_a_proforma_sale_receipt()
    {
        $receiptContent = $this->loadTestFile('3.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        $this->assertSame(ReceiptType::ProformaSale, $receipt->type);

        $this->assertCount(3, $receipt->items);
        $this->assertCount(1, $receipt->taxItems);

        $this->assertSame(1500_00, $receipt->totalPurchaseAmount->getParas());
        $this->assertSame(250_00, $receipt->totalTaxAmount->getParas());
    }

    /** @test */
    public function it_can_properly_parse_shorter_unit_identifiers()
    {
        $receiptContent = $this->loadTestFile('4.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        $this->assertCount(5, $receipt->items);

        // 0.5L AQUA VIVA VODA  FL (Ђ)
        $this->assertSame('0.5L AQUA VIVA VODA', $receipt->items[0]->name);
        $this->assertSame('FL', $receipt->items[0]->unit);

        // 18.5G NESCAFE CAPP.V KO (Ђ)
        $this->assertSame('18.5G NESCAFE CAPP.V', $receipt->items[1]->name);
        $this->assertSame('KO', $receipt->items[1]->unit);
    }

    /** @test */
    public function it_can_parse_unit_identifiers_glued_to_item_name()
    {
        $receiptContent = $this->loadTestFile('5.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        // ESPRESSO/kom (Ђ)
        $this->assertSame('ESPRESSO', $receipt->items[0]->name);
        $this->assertSame('KOM', $receipt->items[0]->unit);

        // CEDJENA NARANDZA 0.2/kom (Ђ)
        $this->assertSame('CEDJENA NARANDZA 0.2', $receipt->items[2]->name);
        $this->assertSame('KOM', $receipt->items[2]->unit);
    }

    /** @test */
    public function it_can_parse_taxes_with_multiple_words_in_the_name()
    {
        $receiptContent = $this->loadTestFile('6.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        $this->assertCount(2, $receipt->taxItems);
        $this->assertSame('О-ПДВ', $receipt->taxItems[0]->tax->name);
        $this->assertSame('Без ПДВ', $receipt->taxItems[1]->tax->name);
    }

    /** @test */
    public function it_can_parse_receipts_where_number_spans_multiple_lines()
    {
        $receiptContent = $this->loadTestFile('6.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        $this->assertSame('9VMEKMSN-DBV8GPO0-1599033', $receipt->number);
        $this->assertSame('1598723/1599033ПП', $receipt->counter);
    }

    /** @test */
    public function it_can_parse_receipts_where_unit_is_separated_by_backslash()
    {
        $receiptContent = $this->loadTestFile('7.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        // BATERIJE\KO (Ђ)
        $this->assertSame('BATERIJE', $receipt->items[0]->name);
        $this->assertSame('KO', $receipt->items[0]->unit);
    }

    /** @test */
    public function it_will_omit_item_code_placed_at_the_beginning_of_the_item_line()
    {
        $receiptContent = $this->loadTestFile('8.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        // 0252491 TRAKA ZA PROZORE I VRATA "D"-BRAON 9MM X 6 KOM
        $this->assertSame('TRAKA ZA PROZORE I VRATA "D"-BRAON 9MM X 6', $receipt->items[0]->name);
    }

    /** @test */
    public function it_will_omit_item_code_placed_at_the_end_of_the_item_line()
    {
        $receiptContent = $this->loadTestFile('9.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        // OPTI BMB 95,2710124500 /LIT (Ђ)
        $this->assertSame('OPTI BMB 95', $receipt->items[0]->name);
    }

    /** @test */
    public function it_can_parse_receipts_without_qr_code_element()
    {
        $receiptContent = $this->loadTestFile('10.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        $this->assertSame('Dzemper', $receipt->items[0]->name);
        $this->assertEmpty($receipt->qrCode);
    }

    /** @test */
    public function it_can_parse_receipts_where_unit_is_wrapped_in_brackets()
    {
        $receiptContent = $this->loadTestFile('11.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        // Pesto (kom) (А)
        $this->assertSame('Pesto', $receipt->items[0]->name);
        // Blue cheese (kom) (А)
        $this->assertSame('Blue cheese', $receipt->items[4]->name);

        $this->assertSame('А', $receipt->taxItems[0]->tax->identifier);
        $this->assertSame('Није у ПДВ', $receipt->taxItems[0]->tax->name);
    }

    /** @test */
    public function it_can_parse_normal_refund_receipts()
    {
        $receiptContent = $this->loadTestFile('12.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        $this->assertSame(ReceiptType::NormalRefund, $receipt->type);

        $this->assertSame('BODI ŽERSEJ - MAJICA', $receipt->items[0]->name);
        $this->assertSame(-599_00, $receipt->items[0]->totalAmount->getParas());

        $this->assertSame('DONJI VEŠ PIDŽAMA', $receipt->items[1]->name);
        $this->assertSame(-1299_00, $receipt->items[1]->totalAmount->getParas());

        $this->assertSame(1898_00, $receipt->totalRefundAmount->getParas());
        $this->assertSame(0, $receipt->totalPurchaseAmount->getParas());

        $this->assertSame(316_33, $receipt->totalTaxAmount->getParas());
    }

    /** @test */
    public function it_will_omit_blank_spaces_when_parsing_receipt_number()
    {
        $receiptContent = $this->loadTestFile('13.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        $this->assertSame('GZWTHS6F-GZWTHS6F-214275', $receipt->number);
    }

    /** @test */
    public function it_can_parse_receipts_with_items_that_have_three_or_more_numbers_in_their_name()
    {
        $receiptContent = $this->loadTestFile('14.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        $this->assertCount(5, $receipt->items);

        // Kuhinjski ubrus Maxi 2 1 2sl/KOM (Ђ)
        $this->assertSame('Kuhinjski ubrus Maxi 2 1 2sl', $receipt->items[1]->name);
    }

    /** @test */
    public function it_can_parse_receipt_items_with_item_code_found_after_the_unit()
    {
        $receiptContent = $this->loadTestFile('15.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        // Beskvasni hleb/KOM/7011623 (Е)
        $this->assertSame('Beskvasni hleb', $receipt->items[0]->name);

        // Filet lososa/KG/0238062 (Е)
        $this->assertSame('Filet lososa', $receipt->items[2]->name);
        $this->assertSame('KG', $receipt->items[2]->unit);
    }

    /** @test */
    public function it_can_parse_receipt_items_with_square_brackets_around_item_codes_and_units()
    {
        $receiptContent = $this->loadTestFile('16.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        // [528195] KASIKA PLASTICNA BELA FRESH 20/1  [KOM] (Ђ)
        $this->assertSame('KASIKA PLASTICNA BELA FRESH 20/1', $receipt->items[0]->name);
    }

    /** @test */
    public function it_can_parse_receipts_where_company_name_spans_multiple_lines()
    {
        $receiptContent = $this->loadTestFile('17.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        $this->assertSame('N COPY CENTAR РАДЊА ЗА ПРОМЕТ РОБЕ НА ВЕЛИКО И МАЛО ФОТОКОПИРАЊЕ И СЕРВИСИРАЊЕ БИРО-ОПРЕМЕ САША МИЛИВОЈЕВИЋ ПР', $receipt->store->companyName);
        $this->assertSame('1079580', $receipt->store->locationId);
        $this->assertSame('N COPY CENTAR', $receipt->store->locationName);
        $this->assertSame('РАЈИЋЕВА 1', $receipt->store->address);
        $this->assertSame('Ниш-Медијана', $receipt->store->city);

        $this->assertSame('ОПЕРАТОР 30', $receipt->meta['Касир']);

        $this->assertSame('A4 jed 1-5', $receipt->items[0]->name);
    }

    /** @test */
    public function it_can_parse_receipt_items_where_unit_is_separated_by_slash_with_surrounding_spaces()
    {
        $receiptContent = $this->loadTestFile('18.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        // 406726134024 M. JEANS / KOM (Ђ)
        $this->assertSame('M. JEANS', $receipt->items[1]->name);
        $this->assertSame('KOM', $receipt->items[1]->unit);
    }

    /** @test */
    public function it_can_parse_advance_sale_receipt()
    {
        $receiptContent = $this->loadTestFile('19.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        $this->assertInstanceOf(Receipt::class, $receipt);

        $this->assertSame(ReceiptType::AdvanceSale, $receipt->type);

        $this->assertCount(5, $receipt->items);
        $this->assertCount(1, $receipt->taxItems);

        $this->assertSame(6370_00, $receipt->totalPurchaseAmount->getParas());
        $this->assertSame(1061_67, $receipt->totalTaxAmount->getParas());

        // Biba Toys igračka mekana kocka za bebe - 26648/kom (Ђ)
        $this->assertSame('Biba Toys igračka mekana kocka za bebe', $receipt->items[0]->name);
        // Avent flašica za bebe natural response 260ml 9639 - SCY903/01 - 32965/kom (Ђ)
        $this->assertSame('Avent flašica za bebe natural response 260ml 9639 - SCY903/01', $receipt->items[1]->name);
    }

    /** @test */
    public function it_can_differentiate_between_units_and_units_that_are_found_as_item_name_suffix()
    {
        $receiptContent = $this->loadTestFile('20.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        // CLEANIC BLAZ. 107KOM (Ђ)
        $this->assertSame('CLEANIC BLAZ. 107KOM', $receipt->items[1]->name);

        // alverde gel/ tuširanje 250ml (Ђ)
        $this->assertSame('alverde gel/ tuširanje 250ml', $receipt->items[3]->name);
    }

    /** @test */
    public function it_can_differentiate_between_item_codes_and_codes_that_are_found_as_item_name_prefix()
    {
        $receiptContent = $this->loadTestFile('21.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        // 200/1 FRESH ŠTAPIAI  KO (Ђ)
        $this->assertSame('200/1 FRESH ŠTAPIAI', $receipt->items[0]->name);
        $this->assertSame('KO', $receipt->items[0]->unit);

        // 100/1 AVIS PAP. KORP KO (Ђ)
        $this->assertSame('100/1 AVIS PAP. KORP', $receipt->items[3]->name);
        $this->assertSame('KO', $receipt->items[3]->unit);

        // 0.5L MG MIVELA VODA  FL (Ђ)
        $this->assertSame('0.5L MG MIVELA VODA', $receipt->items[4]->name);
        $this->assertSame('FL', $receipt->items[4]->unit);
    }

    /** @test */
    public function it_can_parse_receipt_items_where_unit_is_not_separated_from_the_name()
    {
        $receiptContent = $this->loadTestFile('22.txt');

        $parser = new Parser();
        $receipt = $parser->parseJournal($receiptContent);

        // ESPRESSO SA MLEKOM(KOM) (Ђ)
        $this->assertSame('ESPRESSO SA MLEKOM', $receipt->items[0]->name);
        $this->assertSame('KOM', $receipt->items[0]->unit);

        // KNJAZ MILOS 0.25(KOM) (Ђ)
        $this->assertSame('KNJAZ MILOS 0.25', $receipt->items[1]->name);
        $this->assertSame('KOM', $receipt->items[1]->unit);
    }

    /** @test */
    public function it_can_parse_api_response()
    {
        $json = $this->loadTestFile('1.json');
        $data = json_decode($json, true);

        $parser = new Parser();
        $receipt = $parser->parseApiResponse($data);

        $this->assertInstanceOf(Receipt::class, $receipt);

        $store = $receipt->store;
        $this->assertInstanceOf(Store::class, $store);
        $this->assertSame('Mercator-S doo', $store->companyName);
        $this->assertSame('101670560', $store->tin);
        $this->assertSame('1108934', $store->locationId);
        $this->assertSame('Roda Megamarket 345', $store->locationName);
        $this->assertSame('ВИЗАНТИЈСКИ БУЛЕВАР 1', $store->address);
        $this->assertSame('Ниш-Медијана', $store->city);

        $this->assertSame('746DUV64-746DUV64-16898', $receipt->number);
        $this->assertSame('16887/16898ПП', $receipt->counter);
        $this->assertSame(ReceiptType::NormalSale, $receipt->type);
        $this->assertCount(2, $receipt->meta);
        $this->assertSame('431/2.0.0.2', $receipt->meta['ЕСИР број']);

        $this->assertCount(5, $receipt->items);
        $firstItem = $receipt->items[0];
        $this->assertInstanceOf(ReceiptItem::class, $firstItem);

        $this->assertSame('JABUKA ZLATNI DELISES', $firstItem->name);
        $this->assertSame(1.066, $firstItem->quantity);
        $this->assertSame('KG', $firstItem->unit);
        $this->assertSame('Е', $firstItem->tax->identifier);
        $this->assertSame(119_99, $firstItem->singleAmount->getParas());
        $this->assertSame(127_91, $firstItem->totalAmount->getParas());

        $fourthItem = $receipt->items[3];
        $this->assertSame('KESA VJZ 7KG 51 MIKRON', $fourthItem->name);
        $this->assertSame('KOM', $fourthItem->unit);
        $this->assertSame(1.0, $fourthItem->quantity);

        $this->assertCount(2, $receipt->taxItems);
        $firstTax = $receipt->taxItems[0];
        $this->assertInstanceOf(TaxItem::class, $firstTax);
        $this->assertSame('П-ПДВ', $firstTax->tax->name);
        $this->assertSame(10, $firstTax->tax->rate);
        $this->assertSame(74_19, $firstTax->amount->getParas());

        $this->assertSame(1000_00, $receipt->paymentSummary['Готовина']->getParas());

        $this->assertSame(829_12, $receipt->totalPurchaseAmount->getParas());
        $this->assertSame(0, $receipt->totalRefundAmount->getParas());
        $this->assertSame(76_36, $receipt->totalTaxAmount->getParas());

        $this->assertSame('2022-12-31 15:51:57', $receipt->date->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_will_throw_an_error_if_data_doesnt_contain_all_relevant_fields()
    {
        $this->expectException(ParsingException::class);

        $data = [
            'invoiceRequest' => [],
            //'invoiceResult' => [], -- this field is missing
            'journal' => '',
        ];

        $parser = new Parser();
        $parser->parseApiResponse($data);
    }
}
