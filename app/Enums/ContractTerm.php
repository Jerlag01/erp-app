<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Kind of employment contract (Belgian law).
 */
enum ContractTerm: string implements HasLabel
{
    case Indefinite = 'indefinite';

    case FixedTerm = 'fixed_term';

    case SpecificWork = 'specific_work';

    case Replacement = 'replacement';

    case Student = 'student';

    case Interim = 'interim';

    case Flexi = 'flexi';

    public function getLabel(): string
    {
        return __('erp.enums.contract_term.'.$this->value);
    }
}
