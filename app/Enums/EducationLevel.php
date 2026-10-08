<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Highest education degree, following the Belgian levels.
 */
enum EducationLevel: string implements HasLabel
{
    case None = 'none';

    case Primary = 'primary';

    case LowerSecondary = 'lower_secondary';

    case HigherSecondary = 'higher_secondary';

    case SecondaryVocational = 'secondary_vocational';

    case Graduate = 'graduate';

    case Bachelor = 'bachelor';

    case Master = 'master';

    case Doctorate = 'doctorate';

    public function getLabel(): string
    {
        return __('erp.enums.education_level.'.$this->value);
    }
}
