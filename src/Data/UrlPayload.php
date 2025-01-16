<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts\Data;

use DateTimeImmutable;

class UrlPayload
{
    public function __construct(
        public readonly int $version,
        public readonly string $requestedBy,
        public readonly string $signedBy,
        public readonly int $totalCounter,
        public readonly int $transactionTypeCounter,
        public readonly int $totalAmount,
        public readonly int $dateAndTime,
        public readonly int $invoiceType,
        public readonly int $transactionType,
        public readonly int $buyerIdLength,
        public readonly ?string $buyerId,
        public readonly string $encryptedInternalData,
        public readonly string $signature,
        public readonly string $hash,
    )
    {
    }

    public function getReceiptType(): ReceiptType
    {
        return ReceiptType::get($this->invoiceType, $this->transactionType);
    }

    public function getTotalAmount(): RsdAmount
    {
        $stringValue = (string)($this->totalAmount / 10_000);

        @[$integer, $fraction] = explode('.', $stringValue, 2);

        $integer = intval($integer);
        $fraction = intval($fraction);

        return new RsdAmount($integer, $fraction);
    }

    public function getBuyerId(): ?string
    {
        return $this->buyerId;
    }

    public function getReceiptNumber(): string
    {
        return "{$this->requestedBy}-{$this->signedBy}-{$this->totalCounter}";
    }

    public function getReceiptCounter(): string
    {
        $type = $this->getReceiptType();

        return "{$this->transactionTypeCounter}/{$this->totalCounter}{$type->value}";
    }

    public function getReceiptDate(): \DateTimeImmutable
    {
        $time = $this->dateAndTime / 1000;
        $format = ($time == round($time)) ? 'U' : 'U.v';

        $date = DateTimeImmutable::createFromFormat($format, (string)$time);

        return $date->setTimezone(new \DateTimeZone('Europe/Belgrade'));
    }
}
