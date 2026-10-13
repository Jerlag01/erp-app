<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Something an employee asked for that an administrator approves or rejects.
 */
enum RequestStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';

    case Approved = 'approved';

    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __('erp.enums.request_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
