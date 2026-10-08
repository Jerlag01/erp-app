<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The kind of building a work site (COW code) is.
 */
enum BuildingType: string implements HasColor, HasLabel
{
    case Atmk = 'atmk';

    case Ldc = 'ldc';

    case Ec = 'ec';

    case Colibri = 'colibri';

    case Decommissioned = 'decommissioned';

    public function getLabel(): string
    {
        return __('erp.enums.building_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Atmk => 'info',
            self::Ldc => 'primary',
            self::Ec => 'success',
            self::Colibri => 'warning',
            self::Decommissioned => 'danger',
        };
    }
}
