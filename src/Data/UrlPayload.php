<?php

namespace Turanjanin\FiscalReceipts\Data;

use DateTimeImmutable;
use InvalidArgumentException;

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
        $matrix = [
            0 => [
                0 => ReceiptType::NormalSale,
                1 => ReceiptType::NormalRefund,
            ],
            1 => [
                0 => ReceiptType::ProformaSale,
                1 => ReceiptType::ProformaRefund,
            ],
            2 => [
                0 => ReceiptType::TrainingSale,
                1 => ReceiptType::ProformaRefund,
            ],
            3 => [
                0 => ReceiptType::AdvanceSale,
                1 => ReceiptType::AdvanceRefund,
            ],
        ];

        if (!isset($matrix[$this->invoiceType])) {
            throw new InvalidArgumentException('Invalid value provided for InvoiceType.');
        }

        if (!isset($matrix[$this->invoiceType][$this->transactionType])) {
            throw new InvalidArgumentException('Invalid value provided for TransactionType.');
        }

        return $matrix[$this->invoiceType][$this->transactionType];
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

        $date = DateTimeImmutable::createFromFormat($format, $time);

        return $date->setTimezone(new \DateTimeZone('Europe/Belgrade'));
    }
}
