<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Absences an administrator records: medical leave, overtime taken as paid leave, family leave
 * (unpaid leave for compelling family reasons, "dwingende redenen"), other unpaid leave and justified
 * absence ("gewettigd afwezig", GA: with a supporting document, possibly on several loose periods).
 */
enum AbsenceType: string implements HasColor, HasIcon, HasLabel
{
    case Medical = 'medical';

    case Overtime = 'overtime';

    case Family = 'family';

    case Unpaid = 'unpaid';

    case Justified = 'justified';

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
            self::Unpaid => 'gray',
            self::Justified => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Medical => Heroicon::OutlinedHeart,
            self::Overtime => Heroicon::OutlinedClock,
            self::Family => Heroicon::OutlinedHome,
            self::Unpaid => Heroicon::OutlinedMinusCircle,
            self::Justified => Heroicon::OutlinedDocumentCheck,
        };
    }

    /**
     * Only sickness needs a certificate; overtime taken as leave, family leave and unpaid leave do not.
     */
    public function needsCertificate(): bool
    {
        return $this === self::Medical;
    }

    /**
     * Where the administrator can add a document: the certificate of sickness, or the proof of a justified absence.
     */
    public function takesDocument(): bool
    {
        return $this === self::Medical || $this === self::Justified;
    }

    /**
     * Justified absences can be entered for several loose periods at once.
     */
    public function allowsSeveralPeriods(): bool
    {
        return $this === self::Justified;
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
            self::Unpaid => '#64748b',
            self::Justified => '#059669',
        };
    }
}
