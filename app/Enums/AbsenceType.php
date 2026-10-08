<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Absences an administrator records: medical leave, overtime taken as paid leave and family
 * leave (unpaid leave for compelling family reasons, "dwingende redenen").
 */
enum AbsenceType: string implements HasColor, HasIcon, HasLabel
{
    case Medical = 'medical';

    case Overtime = 'overtime';

    case Family = 'family';

    public function getLabel(): string
    {
        return __('erp.enums.absence_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Medical => 'danger',
            self::Overtime => 'info',
            self::Family => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Medical => Heroicon::OutlinedHeart,
            self::Overtime => Heroicon::OutlinedClock,
            self::Family => Heroicon::OutlinedHome,
        };
    }

    /**
     * Medical and family reasons are private: colleagues only see that someone is absent.
     */
    public function isPrivate(): bool
    {
        return $this !== self::Overtime;
    }

    /**
     * Calendar colour of the type.
     */
    public function hex(): string
    {
        return match ($this) {
            self::Medical => '#dc2626',
            self::Overtime => '#0284c7',
            self::Family => '#d97706',
        };
    }
}
