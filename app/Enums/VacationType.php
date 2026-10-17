<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * What a leave request is for: vacation days (from the yearly allowance) or overtime taken as paid
 * leave (from the overtime balance of the prestation sheets). Both follow the same approval.
 */
enum VacationType: string implements HasColor, HasIcon, HasLabel
{
    case Vacation = 'vacation';

    case Overtime = 'overtime';

    public function getLabel(): string
    {
        return __('erp.enums.vacation_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Vacation => 'primary',
            self::Overtime => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Vacation => 'heroicon-o-sun',
            self::Overtime => 'heroicon-o-clock',
        };
    }
}
