<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IncidentStatus: string implements HasColor, HasLabel
{
    case New = 'new';

    case InProgress = 'in_progress';

    case Closed = 'closed';

    public function getLabel(): string
    {
        return __('erp.enums.incident_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Closed => 'success',
        };
    }
}
