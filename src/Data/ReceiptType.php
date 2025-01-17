<?php

declare(strict_types=1);

namespace Turanjanin\FiscalReceipts\Data;

use InvalidArgumentException;

/**
 * @see https://tap.suf.purs.gov.rs/Help/view/335701255/%D0%92%D1%80%D1%81%D1%82%D0%B5-%D1%80%D0%B0%D1%87%D1%83%D0%BD%D0%B0-%D0%B8-%D1%82%D1%80%D0%B0%D0%BD%D1%81%D0%B0%D0%BA%D1%86%D0%B8%D1%98%D0%B0/sr-Cyrl-RS
 * @see https://tap.suf.purs.gov.rs/Help/view/1860547201/Invoice-and-Transaction-Types/en-US
 */
enum ReceiptType: string
{
    // Промет Продаја
    case NormalSale = 'ПП';
    // Промет Рефундација
    case NormalRefund = 'ПР';
    // Аванс Продаја
    case AdvanceSale = 'АП';
    // Аванс Рефундација
    case AdvanceRefund = 'АР';
    // Обука Продаја
    case TrainingSale = 'ОП';
    // Обука Рефундација
    case TrainingRefund = 'ОР';
    // Копија Продаја
    case CopySale = 'КП';
    // Копија Рефундација
    case CopyRefund = 'КР';
    // Предрачун Продаја
    case ProformaSale = 'РП';
    // Предрачун Рефундација
    case ProformaRefund = 'РР';

    public static function get(int $invoiceType, int $transactionType): self
    {
        $matrix = [
            0 => [
                0 => self::NormalSale,
                1 => self::NormalRefund,
            ],
            1 => [
                0 => self::ProformaSale,
                1 => self::ProformaRefund,
            ],
            2 => [
                0 => self::CopySale,
                1 => self::CopyRefund,
            ],
            3 => [
                0 => self::TrainingSale,
                1 => self::TrainingRefund,
            ],
            4 => [
                0 => self::AdvanceSale,
                1 => self::AdvanceRefund,
            ],
        ];

        if (!isset($matrix[$invoiceType])) {
            throw new InvalidArgumentException('Invalid value provided for invoice type.');
        }

        if (!isset($matrix[$invoiceType][$transactionType])) {
            throw new InvalidArgumentException('Invalid value provided for transaction type.');
        }

        return $matrix[$invoiceType][$transactionType];
    }
}
