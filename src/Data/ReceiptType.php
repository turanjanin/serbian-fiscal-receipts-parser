<?php

namespace Turanjanin\FiscalReceipts\Data;

/**
 * @see https://tap.suf.purs.gov.rs/Help/view/331518061/%D0%92%D1%80%D1%81%D1%82%D0%B5-%D1%80%D0%B0%D1%87%D1%83%D0%BD%D0%B0-%D0%B8-%D1%82%D1%80%D0%B0%D0%BD%D1%81%D0%B0%D0%BA%D1%86%D0%B8%D1%98%D0%B0/sr-Cyrl-RS
 * @see https://tap.suf.purs.gov.rs/Help/view/1046938500/Invoice-and-Transaction-Types/en-US
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
}
