<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * What a leave request is for: vacation days (from the yearly allowance), overtime taken as paid leave
 * (from the overtime balance), mobility leave (MV, earned with the kilometres of last year), seniority
 * leave (AN, after 25 years in service) or sollicitatieverlof (SV, one day a week during the notice
 * period). All follow the same approval; once approved, all but vacation become an absence.
 */
enum VacationType: string implements HasColor, HasIcon, HasLabel
{
    case Vacation = 'vacation';

    case Overtime = 'overtime';

    case Mobility = 'mobility';

    case Seniority = 'seniority';

    case JobSearch = 'job_search';

    public function getLabel(): string
    {
        return __('erp.enums.vacation_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Vacation => 'primary',
            self::Overtime, self::Mobility => 'info',
            self::Seniority, self::JobSearch => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Vacation => 'heroicon-o-sun',
            self::Overtime => 'heroicon-o-clock',
            self::Mobility => 'heroicon-o-truck',
            self::Seniority => 'heroicon-o-star',
            self::JobSearch => 'heroicon-o-briefcase',
        };
    }

    /**
     * The absence an approved request becomes (none for vacation, which stays a request).
     */
    public function absenceType(): ?AbsenceType
    {
        return match ($this) {
            self::Vacation => null,
            self::Overtime => AbsenceType::Overtime,
            self::Mobility => AbsenceType::Mobility,
            self::Seniority => AbsenceType::Seniority,
            self::JobSearch => AbsenceType::JobSearch,
        };
    }
}
