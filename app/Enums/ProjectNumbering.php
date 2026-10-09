<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * How the projects of a category are numbered:
 * - Sequence: category, 3-digit number and the COW code of the work site, e.g. 604-001-02GAM.
 * - Yearly: category, 2-digit year + 3-digit number and the COW code, e.g. 605-26001-02GAM.
 * - Private: category and 3-digit number for private / residential clients, e.g. 606-001.
 * - Intervention: numbered like Sequence (600-001-02GAM); while the interventions module is on these
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

    public function formatNumber(int $number): string
    {
        return str_pad((string) $number, $this === self::Yearly ? 5 : 3, '0', STR_PAD_LEFT);
    }
}
