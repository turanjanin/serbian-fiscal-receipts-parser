<?php

namespace Turanjanin\FiscalReceipts\Tests;

use Turanjanin\FiscalReceipts\Data\ReceiptType;
use Turanjanin\FiscalReceipts\Data\UrlPayload;
use Turanjanin\FiscalReceipts\Exceptions\InvalidUrlException;
use Turanjanin\FiscalReceipts\UrlDecoder;

class UrlDecoderTest extends TestCase
{
    /** @test */
    public function it_can_decode_valid_url()
    {
        $data = UrlDecoder::decode('https://suf.purs.gov.rs/v/?vl=A1ZCTUhYOVNYVzZVQlBaTzCyKwEA%2FmgAAMDh5AAAAAAAAAABhXchESoAAAAP3tYiO2%2BdI6Z5y2v4eC5wJTxirHDeiB1hqaKpgb%2FGvUy6yLkMNgZNqKxLqR40mK2cAfqZmKQ3%2BuCcTbec%2BQ3%2F9YY5EhTDP5HxDNhG%2FugU849FmvrVzP0sKecosSNL10dFtlH8Wgor2A2DDs8sHlmfmpokJnVcm24b%2BCz2bSCSl3HtzGRJ1w4Sw9hhdzsQ4WuPo%2FMEGMlmV8a%2Ffc7X05cWsDCHZoA5uPNWfN%2Bre8%2By5JETDJgRwNDFipYIdh0k62TMp5P0%2FzbCueIJJjas5IxAS9iIdpoTAIIl3eKwUZUWvEwtbGz5nkz52hw5%2Bmg50Uczx1SRifYq%2FEDt79xNkcceS0llpMyNdQ12TSYyL0UjMNymgGX4WPajSzPkQuFBcGLB%2BNLOn2AKLPJXa3B8b87eESXrcIbilNXS3zyr3eg4DIqcTVLXwHwcSh1WDmWKI2TFSu%2Bc6iORB11ln1kYbsEsuCoUegxRJR3RW4%2BkQz45%2Bbm4O5qWTCkDlZ73XHATWPn%2BpPfHP2Fh0Y0QK8gGxNiqrdbob3u0l8uaxKcEDaX%2F4HXnhMezvLEEwBNgWXDMn29uWYx9SWEvPrxV%2FLsIULQbE%2FlcvPeYIla63NhCyuEuGLIlwB2p%2B9O8x7sxD53fTMC7EKKRFUV13WBJS2N5%2BLUh33joYo8Qrc%2BNV2CqrtChYTftFukoKbQvCUKOYYIW0%2FA%3D');

        $this->assertInstanceOf(UrlPayload::class, $data);

        $this->assertSame(3, $data->version);
        $this->assertSame(null, $data->getBuyerId());

        $this->assertSame('VBMHX9SX', $data->requestedBy);
        $this->assertSame('W6UBPZO0', $data->signedBy);
        $this->assertSame(76722, $data->totalCounter);
        $this->assertSame(26878, $data->transactionTypeCounter);

        $this->assertSame(15000000, $data->totalAmount);

        $this->assertSame(0, $data->invoiceType);
        $this->assertSame(0, $data->transactionType);

        $this->assertSame(ReceiptType::NormalSale, $data->getReceiptType());
        $this->assertSame('VBMHX9SX-W6UBPZO0-76722', $data->getReceiptNumber());
        $this->assertSame('26878/76722ПП', $data->getReceiptCounter());
        $this->assertSame(1500_00, $data->getTotalAmount()->getParas());
        $this->assertSame('2023-01-03 11:15:33', $data->getReceiptDate()->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_can_properly_decode_total_amount()
    {
        $data = UrlDecoder::decode('https://suf.purs.gov.rs/v/?vl=A0dVNzVOWlpHR1U3NU5aWkcq3QAAKt0AAHzn1wAAAAAAAAABhXIumZ8AAABTgZgutI3zc0%2F8fvWqiySgesurZsQMGOjNpAUF0cQSURljycvOsWCSNvkkIYCP6PFNWGBrnQ3z5BpiMMQQIKin2Ed9YoyNbkJXuHJymVWtR0gaZMmVVcVMTc1X8KaUNbvVYkcTeXYFrjfHoXAymB84OXa1%2FBFJCjlQ0PM8vBOdHoyvrhLnSNnS5iiYEBJgxaGnfejWOVufSs4hgBzRFvtjKJmzNXpGxYi7CzUvE1EbiZkOVQ6UzA9bcrX1pzh80W1HdvkNb77ExtAXOA1u%2F3Yww2DTlYBueUSoqlXLnOEieiXjnRyBtVQXcoeRRuzpE%2BPlCBBmS24wuHy5vCuJ%2B5DXMOpLA3tpHXUmiLzdr9fJOk7QJkZ%2BhkHb0BvWdXuoxFBKlEibJDGFhk7qtfciy2u9z3qDdNsIMa%2FuRZgP8mtVXD2KhIqN%2FXQDevo4cmX8Pc6XHoBIjIY9Xk7Fmel%2Fdc%2FcxaaA2VssxpIjEd8uHcoXsNGL4kXLz8iN%2FpGVunM%2FYkpx1haVPsAZpsK4JTg3QFcqtXmY%2FCPz4227fQS8DtjJiwgGIyqDikaw1e8GkbhqjU3blTovi2SbMSMHJy7SVWyfZsyV8oKVlsPu5u%2BHSa%2BBK%2FSlpP5YMcW6bWI9LAkgNSgRk7d%2B%2B6hSr2elIHFI458NTaXZn5LtbrOaLUjoakdb%2BYPxjmJoSAzPbLfxg8AOioQ%3D');

        $this->assertSame(1414_95, $data->getTotalAmount()->getParas());
    }

    /** @test */
    public function it_can_properly_decode_proforma_sale_receipt_urls()
    {
        $data = UrlDecoder::decode('https://suf.purs.gov.rs/v/?vl=A1ZCTUhYOVNYUjdXUzVNTzAFCwEALK4AAMDh5AAAAAAAAAABhXcDIwIBAAAHiPXrNixGe7w7uGaVPY0d63o0y1CmTNOyn%2BUWnitPJW7LIB50xADV9%2FYL3kmNs7N1k2o%2B3bsTYuTEbssmYh2eGR6VO9YikQYRn515dQ31v2bCDhofTRRGvrfCOe04pvX7wrKB8hT99ynw4KAr8CReHfIaTBrowfbAUTaNf50eB0P10py%2Fk8Mnxjyz1hs2DVBHwlYXRGf%2F74%2F0Jct2OBH6XNT4z9EYAGFIpt2c4zMf3nYcDHrjJUEnSxi5029Bh9FG3i%2BpZGQk84VgmvY%2FcRdFmNTwsVMlPKcpQUKU7r5cPvH0F0eXXmRuM25Up7Gw6klFO%2BJq9L0Rfx7i54nd6KKdPRtlo41P3ZcNvVhVKDFSFJSZygor%2BaC5I%2FsUaVl8rRvxiW3pMjNsVajbylpGGvWBqiLsMYAFGhDkqnyF1snrFlMj4cScKO5CjIRGH364HE9rVZXrHTC7SjdbCHuwlgmhYUF2UcXcnks86FJ9fR2pAOXvB6A9%2Bjuo8sxFFxdZPOgRNU7c4LxHmckrcsETdF8BBmuw14aswljf4qZhYaOVL2E2R0xNakdOpU1UQ%2B67mMKDSeDklLGo7ohiDRNFL7%2BssVRUvUN1Vp8Sw4HbpTZQ6Y3nUywAPuVQ4Y08f5IM6Oke5A%2FtKgA2hRFK8rdV6nNZr%2FMqa8xzgAjLvUc63sHjcLA%2BmdnG5C279kkAMW65ZQY%3D');

        $this->assertSame(ReceiptType::ProformaSale, $data->getReceiptType());
    }

    /** @test */
    public function it_can_decode_buyer_id_from_receipt_url()
    {
        $data = UrlDecoder::decode('https://suf.purs.gov.rs/v/?vl=A0VIR0s5OUVURUhHSzk5RVRHEQMA8QwDALi0kQEAAAAAAAABh0zrdMAAABQAAAAAAAAAADEwOjEwMDE4MTczMo38wGpJqiNIKLwERNC5XE8C1UaxA5cxHmiyV%2fcCFLpITSs2lRzp9KMSG%2bIMj8MSz%2fXkbpVz3PNrjDYUwrxRCdHbUAUz0YitXgWn3cZ1x1F8scXJ%2f%2fnERpTO4436TmVrlUBZMJnuI19hcCFovYK7PDn0MUNoxAg%2f%2bVe6CmKXxF%2b2n6xRLQIHrrlgmv4%2bNpjYJ8jFZWfG08sHCVQq3yyYOmYMBmN13W3v%2fkzcbiS4%2f%2fHfcB%2fBJuyzhdIk%2b4BjJUzQl3eoBaPisjZGIfVMGOwwP1m63VKWy7x1jnLmnTGdeMX4q8Kw1KxMkxkfFfV5VHVv1xby68kmtbbOh55f7dwLSkCqZiFJpCKoHqtYYHox9OREV83vrTwDw%2b5CdvQw5PEgD5up9JZ4QnCssycq2XM3ap6z9BrmMhBg4ODWgtdqwO414RmGU6Egznm1nASQj1AdMbrPGbfBXBcf81KG8Lwe3%2b1KQaJItq6Z%2f8lupSeYJG%2bfFib%2fYOn9SnVrbN6EkqvtUrgiTsPeBkoBlWKkDedzWdRWGpG319fauV1f4R4kz30eEQpRW0NT%2b3a%2b6vBDNm79kEZN7vC8EbtT7ejj8QBGDR5XzBCSmGG%2bZBfuAV7JKWnTeXkACgyqh8j544o5mUUb%2fNiEO5fmIy%2bOwcpgJexigFcvolIbndv8TiKip%2fcGwwQgdz1Z%2f10lwLtbLghm0Xyr5g%3d%3d');

        $this->assertSame(20, $data->buyerIdLength);
        $this->assertSame('10:100181732', $data->buyerId);

        $this->assertSame(2632_62, $data->getTotalAmount()->getParas());
        $this->assertSame('2023-04-04 17:38:32', $data->getReceiptDate()->format('Y-m-d H:i:s'));
    }

    /**
     * @test
     * @testWith  ["https://www.google.com"]
     *            ["random string"]
     */
    public function it_will_throw_an_exception_if_invalid_url_is_provided(string $url)
    {
        $this->expectException(InvalidUrlException::class);

        $data = UrlDecoder::decode($url);
    }

    /** @test */
    public function it_will_throw_an_exception_if_the_hash_does_not_match_the_payload()
    {
        $this->expectException(InvalidUrlException::class);

        $data = UrlDecoder::decode('https://suf.purs.gov.rs/v/?vl=A1ZCTUhYOVNYVzZVQlBaTzCyKwEA%2FmgAAMDh5AAAAAAAAAABhXchESoAAAAP3tYiO2%2BdI6Z5y2v4eC5wJTxirHDeiB1hqaKpgb%2FGvUy6yLkMNgZNqKxLqR40mK2cAfqZmKQ3%2BuCcTbec%2BQ3%2F9YY5EhTDP5HxDNhG%2FugU849FmvrVzP0sKecosSNL10dFtlH8Wgor2A2DDs8sHlmfmpokJnVcm24b%2BCz2bSCSl3HtzGRJ1w4Sw9hhdzsQ4WuPo%2FMEGMlmV8a%2Ffc7X05cWsDCHZoA5uPNWfN%2Bre8%2By5JETDJgRwNDFipYIdh0k62TMp5P0%2FzbCueIJJjas5IxAS9iIdpoTAIIl3eKwUZUWvEwtbGz5nkz52hw5%2Bmg50Uczx1SRifYq%2FEDt79xNkcceS0llpMyNdQ12TSYyL0UjMNymgGX4WPajSzPkQuFBcGLB%2BNLOn2AKLPJXa3B8b87eESXrcIbilNXS3zyr3eg4DIqcTVLXwHwcSh1WDmWKI2TFSu%2Bc6iORB11ln1kYbsEsuCoUegxRJR3RW4%2BkQz45%2Bbm4O5qWTCkDlZ73XHATWPn%2BpPfHP2Fh0Y0QK8gGxNiqrdbob3u0l8uaxKcEDaX%2F4HXnhMezvLEEwBNgWXDMn29uWYx9SWEvPrxV%2FLsIULQbE%2FlcvPeYIla63NhCyuEuGLIlwB2p%2B9O8x7sxD53fTMC7EKFRFUV13WBJS2N5%2BLUh33joYo8Qrc%2BNV2CqrtChYTftFukoKbQvCUKOYYIW0%2FA%3B');
    }
}
