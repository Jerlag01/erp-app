<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ChargingSystem: string implements HasLabel
{
    case Duo = 'duo';

    case Single = 'single';

    public function getLabel(): string
    {
        return match ($this) {
            self::Duo => 'DUO',
            self::Single => 'Single',
        };
    }
}
