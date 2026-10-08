<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The VAT treatments an invoice line can have. Reverse charge ("btw verlegd") charges no VAT:
 * the customer accounts for it, and the invoice must say so.
 */
enum VatRate: string implements HasLabel
{
    case ReverseCharge = 'reverse_charge';

    case Zero = '0';

    case Six = '6';

    case TwentyOne = '21';

    public function getLabel(): string
    {
        return __('erp.enums.vat_rate.'.($this === self::ReverseCharge ? 'reverse_charge' : 'percent'), ['rate' => $this->value]);
    }

    public function percentage(): float
    {
        return match ($this) {
            self::ReverseCharge, self::Zero => 0.0,
            self::Six => 6.0,
            self::TwentyOne => 21.0,
        };
    }

    /**
     * UNCL5305 tax category for Peppol (EN 16931): S standard, Z zero rated, AE reverse charge.
     */
    public function ublCategory(): string
    {
        return match ($this) {
            self::ReverseCharge => 'AE',
            self::Zero => 'Z',
            default => 'S',
        };
    }
}
