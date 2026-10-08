<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VehicleStatus: string implements HasColor, HasLabel
{
    case Active = 'active';

    case Maintenance = 'maintenance';

    case OutOfService = 'out_of_service';

    case Sold = 'sold';

    public function getLabel(): string
    {
        return __('erp.enums.vehicle_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Maintenance => 'warning',
            self::OutOfService => 'danger',
            self::Sold => 'gray',
        };
    }
}
