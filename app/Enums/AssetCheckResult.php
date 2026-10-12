<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The outcome of the yearly control of one piece of equipment.
 */
enum AssetCheckResult: string implements HasColor, HasLabel
{
    case Ok = 'ok';

    case NotOk = 'not_ok';

    public function getLabel(): string
    {
        return __('erp.enums.asset_check_result.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::NotOk => 'danger',
        };
    }
}
