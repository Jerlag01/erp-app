<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Unmatched (not linked to an invoice yet), matched (it paid an invoice or an incoming invoice)
 * or ignored (nothing to link: wages, taxes, transfers between own accounts).
 */
enum BankTransactionStatus: string implements HasColor, HasLabel
{
    case Unmatched = 'unmatched';

    case Matched = 'matched';

    case Ignored = 'ignored';

    public function getLabel(): string
    {
        return __('erp.banking.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unmatched => 'warning',
            self::Matched => 'success',
            self::Ignored => 'gray',
        };
    }
}
