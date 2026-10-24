<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * How the projects of a category are numbered:
 * - Sequence: category, 4-digit number and the COW code of the work site, e.g. 604-0001-02GAM.
 * - Yearly: category, 2-digit year + 4-digit number and the COW code, e.g. 605-260001-02GAM.
 * - Private: category and 4-digit number for private / residential clients, e.g. 606-0001.
 * - Intervention: numbered like Sequence (600-0001-02GAM); while the interventions module is on these
 *   projects also get intervention categories, a closing report and a review (see Interventions).
 */
enum ProjectNumbering: string implements HasDescription, HasLabel
{
    case Sequence = 'sequence';

    case Yearly = 'yearly';

    case Private = 'private';

    case Intervention = 'intervention';

    public function getLabel(): string
    {
        return __('erp.enums.project_numbering.'.$this->value);
    }

    public function getDescription(): string
    {
        return __('erp.enums.project_numbering_help.'.$this->value);
    }

    public function usesWorkSite(): bool
    {
        return $this !== self::Private;
    }

    public function hasPoNumbers(): bool
    {
        return $this !== self::Private;
    }

    public function hasClient(): bool
    {
        return $this === self::Private;
    }

    public function isIntervention(): bool
    {
        return $this === self::Intervention;
    }

    /**
     * Numbers within a year for yearly numbering: 260001–269999 in 2026.
     */
    public const PER_YEAR = 10_000;

    /**
     * 4 digits ("0042"), or for yearly numbering the year and 4 digits ("260042").
     */
    public function formatNumber(int $number): string
    {
        return str_pad((string) $number, $this === self::Yearly ? 6 : 4, '0', STR_PAD_LEFT);
    }
}
