<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How the batteries are set up ("Stelling"): it decides the rack (code and price per cell).
 */
enum BatteryMounting: string implements HasLabel
{
    case Line = 'lijn';

    case UShape = 'u_vorm';

    case Steps = 'trap';

    case Cabinet = 'kast';

    public function getLabel(): string
    {
        return match ($this) {
            self::Line => 'Lijn',
            self::UShape => 'U-Vorm',
            self::Steps => 'Trap',
            self::Cabinet => 'Kast',
        };
    }
}
