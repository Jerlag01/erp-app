<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ItLogAction: string implements HasColor, HasLabel
{
    case Checkout = 'checkout';

    case Checkin = 'checkin';

    case Audit = 'audit';

    case Consume = 'consume';

    case Install = 'install';

    case Remove = 'remove';

    public function getLabel(): string
    {
        return __('erp.enums.it_log_action.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Checkout => 'info',
            self::Checkin => 'success',
            self::Audit => 'gray',
            self::Consume => 'warning',
            self::Install => 'info',
            self::Remove => 'success',
        };
    }
}
