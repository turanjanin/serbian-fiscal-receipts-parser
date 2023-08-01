# Serbian Fiscal Receipts Parser - PHP library

This library allows you to parse the contents of URLs encoded within QR codes on Serbian fiscal receipts.

## Installation

You can install the package via composer:

```bash
composer require turanjanin/serbian-fiscal-receipts-parser
```

This package requires PHP 8.1 or newer. 

It depends on PSR-7 compatible HTTP client for making HTTP requests. If not explicitly provided, 
the package will try to detect and instantiate already existing client class.

## Usage


```php
$url = 'https://suf.purs.gov.rs/v/?vl=A1ZCTUhYOVNYVzZVQlBaTzCyKwEA%2FmgAAMDh5AAAAAAAAAABhXchESoAAAAP3tYiO2%2BdI6Z5y2v4eC5wJTxirHDeiB1hqaKpgb%2FGvUy6yLkMNgZNqKxLqR40mK2cAfqZmKQ3%2BuCcTbec%2BQ3%2F9YY5EhTDP5HxDNhG%2FugU849FmvrVzP0sKecosSNL10dFtlH8Wgor2A2DDs8sHlmfmpokJnVcm24b%2BCz2bSCSl3HtzGRJ1w4Sw9hhdzsQ4WuPo%2FMEGMlmV8a%2Ffc7X05cWsDCHZoA5uPNWfN%2Bre8%2By5JETDJgRwNDFipYIdh0k62TMp5P0%2FzbCueIJJjas5IxAS9iIdpoTAIIl3eKwUZUWvEwtbGz5nkz52hw5%2Bmg50Uczx1SRifYq%2FEDt79xNkcceS0llpMyNdQ12TSYyL0UjMNymgGX4WPajSzPkQuFBcGLB%2BNLOn2AKLPJXa3B8b87eESXrcIbilNXS3zyr3eg4DIqcTVLXwHwcSh1WDmWKI2TFSu%2Bc6iORB11ln1kYbsEsuCoUegxRJR3RW4%2BkQz45%2Bbm4O5qWTCkDlZ73XHATWPn%2BpPfHP2Fh0Y0QK8gGxNiqrdbob3u0l8uaxKcEDaX%2F4HXnhMezvLEEwBNgWXDMn29uWYx9SWEvPrxV%2FLsIULQbE%2FlcvPeYIla63NhCyuEuGLIlwB2p%2B9O8x7sxD53fTMC7EKKRFUV13WBJS2N5%2BLUh33joYo8Qrc%2BNV2CqrtChYTftFukoKbQvCUKOYYIW0%2FA%3D';

$fetcher = new \Turanjanin\FiscalReceipts\Fetcher();

// Get receipt from the given URL. 
$receipt = $fetcher->fetchFromUrl($url);

// Or, if you already have URL content stored in $html variable:
$receipt = $fetcher->fetchFromHtml($html);
```


If you already have a plain text version of the receipt, you can call the Parser class directly:

```php
$parser = new \Turanjanin\FiscalReceipts\Parser();
$receipt = $parser->parse($receiptContent);
```


Receipt class will give you access to the following receipt elements:
- Store data
- Receipt number, counter and type
- List of receipt items
- List of all taxes
- Total amount


Alternatively, you can decode receipt URL and extract some of the data offline, without the need for HTTP calls:

```php
$payload = \Turanjanin\FiscalReceipts\UrlDecoder::decode($url);

$payload->getTotalAmount();
$payload->getBuyerId();
$payload->getReceiptNumber();
$payload->getReceiptCounter();
$payload->getReceiptDate();
```



## Author

- [Jovan Turanjanin](https://github.com/turanjanin)


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
