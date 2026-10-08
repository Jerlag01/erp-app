<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DamageSeverity: string implements HasColor, HasLabel
{
    case Minor = 'minor';

    case Major = 'major';

    case Critical = 'critical';

    public function getLabel(): string
    {
        return __('erp.enums.damage_severity.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Minor => 'gray',
            self::Major => 'warning',
            self::Critical => 'danger',
        };
    }
}
