<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Outcome of the yearly evaluation by the occupational (company) doctor.
 */
enum MedicalResult: string implements HasColor, HasLabel
{
    case Fit = 'fit';

    case FitWithRestrictions = 'fit_with_restrictions';

    case TemporarilyUnfit = 'temporarily_unfit';

    case Unfit = 'unfit';

    public function getLabel(): string
    {
        return __('erp.enums.medical_result.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Fit => 'success',
            self::FitWithRestrictions => 'warning',
            self::TemporarilyUnfit, self::Unfit => 'danger',
        };
    }
}
