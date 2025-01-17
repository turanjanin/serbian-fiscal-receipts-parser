<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts\Tests;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Turanjanin\FiscalReceipts\Data\Receipt;
use Turanjanin\FiscalReceipts\Exceptions\InvalidUrlException;
use Turanjanin\FiscalReceipts\Exceptions\ParsingException;
use Turanjanin\FiscalReceipts\Exceptions\SufException;
use Turanjanin\FiscalReceipts\Fetcher;
use Turanjanin\FiscalReceipts\Tests\Fakes\FakeClient;

class FetcherTest extends TestCase
{
    /** @test */
    public function it_can_fetch_receipt_from_url()
    {
        $url = 'https://suf.purs.gov.rs/v/?vl=Azc0NkRVVjY0NzQ2RFVWNjQCQgAA90EAAICDfgAAAAAAAAABhWirB5IAAABYLV6uN7MeC94JtCh0LAOKsOauDJHBLLBw4Vg0vIkua6eRIzGBw0OtoIghVnz0SpdhaeoRPDtbJri1k1B%2FFK0QurMzZ51F5TIW5vdxssqMeseg5OP%2FFBVeUTCc%2FQBI%2BjVs6echaCZlHSF8IjeOCaBgQ3yW7K1AuUh9d66q6uCR3g66zepLlR1C%2BNrrvodQ4ljdlKsCkjkXyTsALMVvnJwqbs1c6ZV7foKMwtpVKItvaEIcqqxfpa20j5H0vIn6LrOghGCK%2BKH7VxM%2B27%2BEGHK96m2E0G6%2BeQQN1LCGY8sMBQ57idKJ5FhljhK%2FiKCcwZrfE2qyqUC%2Bh%2FR8j7rpDUPLOWnhkGoH3n1LX3NZkoXG04kOeYJe52Q%2B%2FqMtSoNJPGnf7gxlW0A2xm5xQ1DIplX9DYAZHQrLTdOu7n35CxXtdR5KMG6Ygv7qNmANlEFwyww1sjsXkSlz%2FnCCS3vydp2j6vTbNtN20Vtpzeg40t79lZBX6eOYhc8CXRzGfZLOkUymrG1puUpmA3QdzUnqdavZnxp6rElHlSVAoNMJumQ9J48y2OeNvJ7WAcw9jGBkeFNcc3UXsWYKcUHdPiefkomHIZZnOpIq%2F32aXO54%2BPCr4KI7ofQ%2FNtZEEF8r52tAWCjvBsZqRoncxjfHvDqFkHJD4pKA91qVP1IwoBAy1QiU38S3YdSWw%2FlfgmGs1Dso0Qo%3D';

        $client = new FakeClient();
        $client->addResponse(
            new Uri($url),
            new Response(
                200,
                [],
                $this->loadTestFile('1.html')
            )
        );

        $fetcher = new Fetcher($client);
        $receipt = $fetcher->fetchFromUrl($url);

        $this->assertInstanceOf(Receipt::class, $receipt);
        $this->assertSame('MERCATOR-S', $receipt->store->companyName);
        $this->assertSame('ВИЗАНТИЈСКИ БУЛЕВАР 1', $receipt->store->address);
        $this->assertSame('746DUV64-746DUV64-16898', $receipt->number);
    }

    /** @test */
    public function it_can_fetch_receipt_from_url_that_includes_port_number()
    {
        $url = 'https://suf.purs.gov.rs:443/v/?vl=Azc0NkRVVjY0NzQ2RFVWNjQCQgAA90EAAICDfgAAAAAAAAABhWirB5IAAABYLV6uN7MeC94JtCh0LAOKsOauDJHBLLBw4Vg0vIkua6eRIzGBw0OtoIghVnz0SpdhaeoRPDtbJri1k1B%2FFK0QurMzZ51F5TIW5vdxssqMeseg5OP%2FFBVeUTCc%2FQBI%2BjVs6echaCZlHSF8IjeOCaBgQ3yW7K1AuUh9d66q6uCR3g66zepLlR1C%2BNrrvodQ4ljdlKsCkjkXyTsALMVvnJwqbs1c6ZV7foKMwtpVKItvaEIcqqxfpa20j5H0vIn6LrOghGCK%2BKH7VxM%2B27%2BEGHK96m2E0G6%2BeQQN1LCGY8sMBQ57idKJ5FhljhK%2FiKCcwZrfE2qyqUC%2Bh%2FR8j7rpDUPLOWnhkGoH3n1LX3NZkoXG04kOeYJe52Q%2B%2FqMtSoNJPGnf7gxlW0A2xm5xQ1DIplX9DYAZHQrLTdOu7n35CxXtdR5KMG6Ygv7qNmANlEFwyww1sjsXkSlz%2FnCCS3vydp2j6vTbNtN20Vtpzeg40t79lZBX6eOYhc8CXRzGfZLOkUymrG1puUpmA3QdzUnqdavZnxp6rElHlSVAoNMJumQ9J48y2OeNvJ7WAcw9jGBkeFNcc3UXsWYKcUHdPiefkomHIZZnOpIq%2F32aXO54%2BPCr4KI7ofQ%2FNtZEEF8r52tAWCjvBsZqRoncxjfHvDqFkHJD4pKA91qVP1IwoBAy1QiU38S3YdSWw%2FlfgmGs1Dso0Qo%3D';

        $client = new FakeClient();
        $client->addResponse(
            new Uri($url),
            new Response(
                200,
                [],
                $this->loadTestFile('1.html')
            )
        );

        $fetcher = new Fetcher($client);
        $receipt = $fetcher->fetchFromUrl($url);

        $this->assertInstanceOf(Receipt::class, $receipt);
    }

    /** @test */
    public function it_will_throw_an_exception_if_invalid_url_is_provided()
    {
        $this->expectException(InvalidUrlException::class);

        $client = new FakeClient();
        $fetcher = new Fetcher($client);
        $fetcher->fetchFromUrl('https://www.google.com');
    }

    /** @test */
    public function it_will_throw_an_exception_if_suf_server_returns_an_error()
    {
        $url = 'https://suf.purs.gov.rs/v/?vl=INVALID-Azc0NkRVVjY0NzQ2RFVWNjQCQgAA90EAAICDfgAAAAAAAAABhWirB5IAAABYLV6uN7MeC94JtCh0LAOKsOauDJHBLLBw4Vg0vIkua6eRIzGBw0OtoIghVnz0SpdhaeoRPDtbJri1k1B%2FFK0QurMzZ51F5TIW5vdxssqMeseg5OP%2FFBVeUTCc%2FQBI%2BjVs6echaCZlHSF8IjeOCaBgQ3yW7K1AuUh9d66q6uCR3g66zepLlR1C%2BNrrvodQ4ljdlKsCkjkXyTsALMVvnJwqbs1c6ZV7foKMwtpVKItvaEIcqqxfpa20j5H0vIn6LrOghGCK%2BKH7VxM%2B27%2BEGHK96m2E0G6%2BeQQN1LCGY8sMBQ57idKJ5FhljhK%2FiKCcwZrfE2qyqUC%2Bh%2FR8j7rpDUPLOWnhkGoH3n1LX3NZkoXG04kOeYJe52Q%2B%2FqMtSoNJPGnf7gxlW0A2xm5xQ1DIplX9DYAZHQrLTdOu7n35CxXtdR5KMG6Ygv7qNmANlEFwyww1sjsXkSlz%2FnCCS3vydp2j6vTbNtN20Vtpzeg40t79lZBX6eOYhc8CXRzGfZLOkUymrG1puUpmA3QdzUnqdavZnxp6rElHlSVAoNMJumQ9J48y2OeNvJ7WAcw9jGBkeFNcc3UXsWYKcUHdPiefkomHIZZnOpIq%2F32aXO54%2BPCr4KI7ofQ%2FNtZEEF8r52tAWCjvBsZqRoncxjfHvDqFkHJD4pKA91qVP1IwoBAy1QiU38S3YdSWw%2FlfgmGs1Dso0Qo%3D';

        $client = new FakeClient();
        $client->addResponse(
            new Uri($url),
            new Response(
                400,
            )
        );

        $this->expectException(SufException::class);

        $fetcher = new Fetcher($client);
        $receipt = $fetcher->fetchFromUrl($url);
    }

    /** @test */
    public function it_can_fetch_receipt_content_from_url()
    {
        $url = 'https://suf.purs.gov.rs/v/?vl=Azc0NkRVVjY0NzQ2RFVWNjQCQgAA90EAAICDfgAAAAAAAAABhWirB5IAAABYLV6uN7MeC94JtCh0LAOKsOauDJHBLLBw4Vg0vIkua6eRIzGBw0OtoIghVnz0SpdhaeoRPDtbJri1k1B%2FFK0QurMzZ51F5TIW5vdxssqMeseg5OP%2FFBVeUTCc%2FQBI%2BjVs6echaCZlHSF8IjeOCaBgQ3yW7K1AuUh9d66q6uCR3g66zepLlR1C%2BNrrvodQ4ljdlKsCkjkXyTsALMVvnJwqbs1c6ZV7foKMwtpVKItvaEIcqqxfpa20j5H0vIn6LrOghGCK%2BKH7VxM%2B27%2BEGHK96m2E0G6%2BeQQN1LCGY8sMBQ57idKJ5FhljhK%2FiKCcwZrfE2qyqUC%2Bh%2FR8j7rpDUPLOWnhkGoH3n1LX3NZkoXG04kOeYJe52Q%2B%2FqMtSoNJPGnf7gxlW0A2xm5xQ1DIplX9DYAZHQrLTdOu7n35CxXtdR5KMG6Ygv7qNmANlEFwyww1sjsXkSlz%2FnCCS3vydp2j6vTbNtN20Vtpzeg40t79lZBX6eOYhc8CXRzGfZLOkUymrG1puUpmA3QdzUnqdavZnxp6rElHlSVAoNMJumQ9J48y2OeNvJ7WAcw9jGBkeFNcc3UXsWYKcUHdPiefkomHIZZnOpIq%2F32aXO54%2BPCr4KI7ofQ%2FNtZEEF8r52tAWCjvBsZqRoncxjfHvDqFkHJD4pKA91qVP1IwoBAy1QiU38S3YdSWw%2FlfgmGs1Dso0Qo%3D';

        $client = new FakeClient();
        $client->addResponse(
            new Uri($url),
            new Response(
                200,
                [],
                $this->loadTestFile('1.html')
            )
        );

        $fetcher = new Fetcher($client);
        $content = $fetcher->fetchReceiptContent($url);

        $expectedContent = trim($this->loadTestFile('1.txt'));

        $this->assertSame($expectedContent, $content);
    }

    /** @test */
    public function it_can_fetch_receipt_from_html()
    {
        $html = $this->loadTestFile('1.html');

        $client = new FakeClient();
        $fetcher = new Fetcher($client);

        $receipt = $fetcher->fetchFromHtml($html);

        $this->assertInstanceOf(Receipt::class, $receipt);
        $this->assertSame('MERCATOR-S', $receipt->store->companyName);
        $this->assertSame('ВИЗАНТИЈСКИ БУЛЕВАР 1', $receipt->store->address);
        $this->assertSame('746DUV64-746DUV64-16898', $receipt->number);
    }

    /** @test */
    public function it_can_extract_receipt_content_from_html()
    {
        $html = $this->loadTestFile('1.html');

        $client = new FakeClient();
        $fetcher = new Fetcher($client);
        $content = $fetcher->extractReceiptContent($html);

        $expectedContent = trim($this->loadTestFile('1.txt'));

        $this->assertSame($expectedContent, $content);
    }

    /** @test */
    public function it_will_decode_html_entities_when_extracting_receipt_content_from_html()
    {
        $html = $this->loadTestFile('2.html');

        $client = new FakeClient();
        $fetcher = new Fetcher($client);
        $content = $fetcher->extractReceiptContent($html);

        $this->assertStringNotContainsStringIgnoringCase('&amp;', $content);
        $this->assertStringContainsString('H&M HENNES & MAURITZ', $content);
    }

    /**
     * @test
     * @testWith  [""]
     *            ["plain text"]
     *            ["<div>text</div>"]
     *            ["<pre></pre>"]
     */
    public function it_will_throw_an_exception_if_receipt_content_is_not_found(string $html)
    {
        $this->expectException(ParsingException::class);

        $client = new FakeClient();
        $fetcher = new Fetcher($client);
        $content = $fetcher->extractReceiptContent($html);
    }

    /** @test */
    public function it_can_fetch_receipt_data_from_the_json_api()
    {
        $url = 'https://suf.purs.gov.rs/v/?vl=Azc0NkRVVjY0NzQ2RFVWNjQCQgAA90EAAICDfgAAAAAAAAABhWirB5IAAABYLV6uN7MeC94JtCh0LAOKsOauDJHBLLBw4Vg0vIkua6eRIzGBw0OtoIghVnz0SpdhaeoRPDtbJri1k1B%2FFK0QurMzZ51F5TIW5vdxssqMeseg5OP%2FFBVeUTCc%2FQBI%2BjVs6echaCZlHSF8IjeOCaBgQ3yW7K1AuUh9d66q6uCR3g66zepLlR1C%2BNrrvodQ4ljdlKsCkjkXyTsALMVvnJwqbs1c6ZV7foKMwtpVKItvaEIcqqxfpa20j5H0vIn6LrOghGCK%2BKH7VxM%2B27%2BEGHK96m2E0G6%2BeQQN1LCGY8sMBQ57idKJ5FhljhK%2FiKCcwZrfE2qyqUC%2Bh%2FR8j7rpDUPLOWnhkGoH3n1LX3NZkoXG04kOeYJe52Q%2B%2FqMtSoNJPGnf7gxlW0A2xm5xQ1DIplX9DYAZHQrLTdOu7n35CxXtdR5KMG6Ygv7qNmANlEFwyww1sjsXkSlz%2FnCCS3vydp2j6vTbNtN20Vtpzeg40t79lZBX6eOYhc8CXRzGfZLOkUymrG1puUpmA3QdzUnqdavZnxp6rElHlSVAoNMJumQ9J48y2OeNvJ7WAcw9jGBkeFNcc3UXsWYKcUHdPiefkomHIZZnOpIq%2F32aXO54%2BPCr4KI7ofQ%2FNtZEEF8r52tAWCjvBsZqRoncxjfHvDqFkHJD4pKA91qVP1IwoBAy1QiU38S3YdSWw%2FlfgmGs1Dso0Qo%3D';

        $client = new FakeClient();
        $client->addResponse(
            new Uri($url),
            new Response(
                200,
                [],
                $this->loadTestFile('1.json')
            )
        );

        $fetcher = new Fetcher($client);
        $receipt = $fetcher->fetchFromApi($url);

        $this->assertInstanceOf(Receipt::class, $receipt);
        $this->assertSame('Mercator-S doo', $receipt->store->companyName);
        $this->assertSame('ВИЗАНТИЈСКИ БУЛЕВАР 1', $receipt->store->address);
        $this->assertSame('746DUV64-746DUV64-16898', $receipt->number);
    }

    /** @test */
    public function it_will_throw_an_exception_if_invalid_url_is_provided_for_the_api()
    {
        $this->expectException(InvalidUrlException::class);

        $client = new FakeClient();
        $fetcher = new Fetcher($client);
        $fetcher->fetchFromApi('https://www.google.com');
    }

    /** @test */
    public function it_will_throw_an_exception_if_suf_server_returns_an_error_while_fetching_api_endpoint()
    {
        $url = 'https://suf.purs.gov.rs/v/?vl=INVALID-Azc0NkRVVjY0NzQ2RFVWNjQCQgAA90EAAICDfgAAAAAAAAABhWirB5IAAABYLV6uN7MeC94JtCh0LAOKsOauDJHBLLBw4Vg0vIkua6eRIzGBw0OtoIghVnz0SpdhaeoRPDtbJri1k1B%2FFK0QurMzZ51F5TIW5vdxssqMeseg5OP%2FFBVeUTCc%2FQBI%2BjVs6echaCZlHSF8IjeOCaBgQ3yW7K1AuUh9d66q6uCR3g66zepLlR1C%2BNrrvodQ4ljdlKsCkjkXyTsALMVvnJwqbs1c6ZV7foKMwtpVKItvaEIcqqxfpa20j5H0vIn6LrOghGCK%2BKH7VxM%2B27%2BEGHK96m2E0G6%2BeQQN1LCGY8sMBQ57idKJ5FhljhK%2FiKCcwZrfE2qyqUC%2Bh%2FR8j7rpDUPLOWnhkGoH3n1LX3NZkoXG04kOeYJe52Q%2B%2FqMtSoNJPGnf7gxlW0A2xm5xQ1DIplX9DYAZHQrLTdOu7n35CxXtdR5KMG6Ygv7qNmANlEFwyww1sjsXkSlz%2FnCCS3vydp2j6vTbNtN20Vtpzeg40t79lZBX6eOYhc8CXRzGfZLOkUymrG1puUpmA3QdzUnqdavZnxp6rElHlSVAoNMJumQ9J48y2OeNvJ7WAcw9jGBkeFNcc3UXsWYKcUHdPiefkomHIZZnOpIq%2F32aXO54%2BPCr4KI7ofQ%2FNtZEEF8r52tAWCjvBsZqRoncxjfHvDqFkHJD4pKA91qVP1IwoBAy1QiU38S3YdSWw%2FlfgmGs1Dso0Qo%3D';

        $client = new FakeClient();
        $client->addResponse(
            new Uri($url),
            new Response(
                400,
            )
        );

        $this->expectException(SufException::class);

        $fetcher = new Fetcher();
        $receipt = $fetcher->fetchFromApi($url);
    }
}
