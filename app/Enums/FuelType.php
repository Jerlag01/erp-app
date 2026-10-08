<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FuelType: string implements HasColor, HasLabel
{
    case Diesel = 'diesel';

    case Petrol = 'petrol';

    case Hybrid = 'hybrid';

    case Electric = 'electric';

    case Lpg = 'lpg';

    case Cng = 'cng';

    public function getLabel(): string
    {
        return __('erp.enums.fuel.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Diesel => 'gray',
            self::Petrol => 'gray',
            self::Hybrid => 'info',
            self::Electric => 'success',
            self::Lpg => 'gray',
            self::Cng => 'gray',
        };
    }
}
