<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * "Deelfactuur" of the invoice request.
 */
enum BatteryInvoiceKind: string implements HasLabel
{
    case Final = 'final';

    case Partial1 = 'partial_1';

    case Partial2 = 'partial_2';

    case Partial3 = 'partial_3';

    /**
     * The Dutch text on the invoice request, as in the Excel.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Final => 'Eindfactuur',
            self::Partial1 => 'Deelfactuur 1',
            self::Partial2 => 'Deelfactuur 2',
            self::Partial3 => 'Deelfactuur 3',
        };
    }
}
