<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Battery technology as on the Excel input sheet. OPzS (flooded) also gets an acid density per cell
 * on the measurement report.
 */
enum BatteryTechnology: string implements HasLabel
{
    case OPzV = 'OPzV';

    case OPzS = 'OPzS';

    case Tubul = 'Tubul';

    case Plant = 'Plant';

    case Unknown = 'Onbekend';

    public function getLabel(): string
    {
        return $this === self::Unknown ? __('erp.batteries.unknown') : $this->value;
    }

    public function hasDensity(): bool
    {
        return $this === self::OPzS;
    }
}
