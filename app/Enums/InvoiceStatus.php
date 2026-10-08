<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Draft (editable, no number yet) → final (numbered, locked) → sent → paid.
 */
enum InvoiceStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';

    case Final = 'final';

    case Sent = 'sent';

    case Paid = 'paid';

    public function getLabel(): string
    {
        return __('erp.enums.invoice_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Final => 'info',
            self::Sent => 'warning',
            self::Paid => 'success',
        };
    }
}
