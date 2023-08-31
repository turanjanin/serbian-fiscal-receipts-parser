<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts;

use Turanjanin\FiscalReceipts\Data\UrlPayload;
use Turanjanin\FiscalReceipts\Exceptions\InvalidUrlException;

class UrlDecoder
{
    /**
     * @see https://tap.suf.purs.gov.rs/Help/view/612513249/Create-Verification-URL/en-US
     */
    public static function decode(string $url): UrlPayload
    {
        $queryString = parse_url($url, PHP_URL_QUERY) ?? '';
        parse_str($queryString, $queryParts);
        $vl = $queryParts['vl'] ?? '';

        $bytes = base64_decode($vl);

        $numberOfBytes = strlen($bytes);
        if ($numberOfBytes < 572 || $numberOfBytes > 848) {
            throw new InvalidUrlException('The length of payload is out of bounds.');
        }

        $buyerIdLength = unpack('C', substr($bytes, 43, 1))[1];
        $buyerId = null;

        $offset = 44;

        if ($buyerIdLength > 0) {
            $buyerId = trim(substr($bytes, $offset, $buyerIdLength));
            $offset += $buyerIdLength;
        }

        $internalDataLength = ($numberOfBytes > 592) ? 512 : 256;

        $encryptedInternalData = bin2hex(substr($bytes, $offset, $internalDataLength));
        $offset += $internalDataLength;

        $signatureLength = 256;
        $signature = bin2hex(substr($bytes, $offset, $signatureLength));

        $hash = bin2hex(substr($bytes, -16));
        $encodedData = substr($bytes, 0, -16);

        return new UrlPayload(
            version: unpack('C', substr($bytes, 0, 1))[1],
            requestedBy: implode(array_map('chr', unpack('C8', $bytes, 1))),
            signedBy: implode(array_map('chr', unpack('C8', $bytes, 9))),
            totalCounter: unpack('V', $bytes, 17)[1],
            transactionTypeCounter: unpack('V', $bytes, 21)[1],
            totalAmount: unpack('P', $bytes, 25)[1],
            dateAndTime: unpack('J', $bytes, 33)[1],
            invoiceType: unpack('C', substr($bytes, 41, 1))[1],
            transactionType: unpack('C', substr($bytes, 42, 1))[1],
            buyerIdLength: $buyerIdLength,
            buyerId: $buyerId,
            encryptedInternalData: $encryptedInternalData,
            signature: $signature,
            hash: $hash,
            isHashValid: $hash === md5($encodedData)
        );
    }
}
