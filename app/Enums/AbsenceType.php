<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The absences of an employee and how the prestation sheet counts them:
 *
 *  - Z  sickness (certificate), R overtime taken as leave, FV family leave, unpaid leave, GA justified absence;
 *  - MV mobility leave, AN seniority leave, SV sollicitatieverlof: asked by the employee, paid (a full day);
 *  - EW economically unemployed and OV parental leave: not worked (paid by the RVA), but the day is taken
 *    off the expected hours of the week, so the overtime balance stays as it is;
 *  - KV klein verlet (a reason of the royal decree of 28/08/1963, certificate): a full day worked;
 *  - WO work accident, linked to the accident report: paid with a certificate within 48 hours, else unpaid.
 */
enum AbsenceType: string implements HasColor, HasIcon, HasLabel
{
    case Medical = 'medical';

    case Overtime = 'overtime';

    case Family = 'family';

    case Unpaid = 'unpaid';

    case Justified = 'justified';

    case Mobility = 'mobility';

    case Economic = 'economic';

    case ShortLeave = 'short_leave';

    case Seniority = 'seniority';

    case JobSearch = 'job_search';

    case WorkAccident = 'work_accident';

    case Parental = 'parental';

    public function getLabel(): string
    {
        return __('erp.enums.absence_type.'.$this->value);
    }

    /**
     * The code on the prestation sheet: "Z", "MV", "EW" …
     */
    public function code(): string
    {
        return match ($this) {
            self::Medical => 'Z',
            self::Overtime => 'R',
            self::Family => 'FV',
            self::Unpaid => 'OB',
            self::Justified => 'GA',
            self::Mobility => 'MV',
            self::Economic => 'EW',
            self::ShortLeave => 'KV',
            self::Seniority => 'AN',
            self::JobSearch => 'SV',
            self::WorkAccident => 'WO',
            self::Parental => 'OV',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Medical, self::WorkAccident => 'danger',
            self::Overtime, self::Mobility => 'info',
            self::Family, self::ShortLeave => 'warning',
            self::Unpaid, self::Economic => 'gray',
            self::Justified, self::Seniority, self::JobSearch, self::Parental => 'success',
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
            self::Mobility => Heroicon::OutlinedTruck,
            self::Economic => Heroicon::OutlinedPauseCircle,
            self::ShortLeave => Heroicon::OutlinedCalendar,
            self::Seniority => Heroicon::OutlinedStar,
            self::JobSearch => Heroicon::OutlinedBriefcase,
            self::WorkAccident => Heroicon::OutlinedExclamationTriangle,
            self::Parental => Heroicon::OutlinedUserGroup,
        };
    }

    /**
     * A certificate is needed: sickness, klein verlet and a work accident (within 48 hours).
     */
    public function needsCertificate(): bool
    {
        return in_array($this, [self::Medical, self::ShortLeave, self::WorkAccident], true);
    }

    /**
     * Where the administrator can add a document: the certificate, or the proof of a justified absence.
     */
    public function takesDocument(): bool
    {
        return $this->needsCertificate() || $this === self::Justified;
    }

    /**
     * Justified absences can be entered for several loose periods at once.
     */
    public function allowsSeveralPeriods(): bool
    {
        return $this === self::Justified;
    }

    /**
     * Asked by the employee as leave (and approved like vacation), not entered by administration.
     */
    public function isRequested(): bool
    {
        return in_array($this, [self::Overtime, self::Mobility, self::Seniority, self::JobSearch], true);
    }

    /**
     * Only the administration enters these (and changes them); the employee never does.
     */
    public function isAdministrationOnly(): bool
    {
        return in_array($this, [self::Economic, self::ShortLeave, self::WorkAccident, self::Parental], true);
    }

    /**
     * Counts as a full working day on the prestation sheet (the hours of a day are credited).
     */
    public function countsAsWorked(): bool
    {
        return in_array($this, [self::Medical, self::Mobility, self::ShortLeave, self::Seniority, self::JobSearch, self::WorkAccident], true);
    }

    /**
     * Not worked, but the day is taken off the expected hours of the week: the overtime balance stays.
     */
    public function keepsOvertime(): bool
    {
        return in_array($this, [self::Economic, self::Parental], true);
    }

    /**
     * Nothing can be planned on such a day (the other absences only show in the planning).
     */
    public function blocksPlanning(): bool
    {
        return in_array($this, [self::Medical, self::Family, self::WorkAccident], true);
    }

    /**
     * Colleagues only see "absent": the reason is private, except leave the employee freely plans.
     */
    public function isPrivate(): bool
    {
        return ! in_array($this, [self::Overtime, self::Mobility, self::Seniority], true);
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
            self::Mobility => '#0891b2',
            self::Economic => '#475569',
            self::ShortLeave => '#ca8a04',
            self::Seniority => '#7c3aed',
            self::JobSearch => '#16a34a',
            self::WorkAccident => '#b91c1c',
            self::Parental => '#db2777',
        };
    }
}
