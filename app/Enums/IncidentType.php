<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IncidentType: string implements HasColor, HasLabel
{
    case Accident = 'accident';

    case NearMiss = 'near_miss';

    case DangerousSituation = 'dangerous_situation';

    case DangerousAction = 'dangerous_action';

    public function getLabel(): string
    {
        return __('erp.enums.incident_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Accident => 'danger',
            self::NearMiss => 'warning',
            self::DangerousSituation => 'info',
            self::DangerousAction => 'info',
        };
    }
}
