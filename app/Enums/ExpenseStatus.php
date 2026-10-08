<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * An incoming invoice waits for validation by someone with the right, who accepts it (with a
 * note and signature) or rejects it.
 */
enum ExpenseStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';

    case Accepted = 'accepted';

    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __('erp.enums.expense_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }
}
