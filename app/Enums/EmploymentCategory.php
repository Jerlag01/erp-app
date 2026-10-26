<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Belgian employment status categories. "Arbeidsongeschikt" (unfit for work) is set for the time an
 * employee is on long-term sick leave: every working day is then sickness (Z) on the prestation sheet.
 */
enum EmploymentCategory: string implements HasLabel
{
    case Worker = 'worker';

    case Employee = 'employee';

    case Manager = 'manager';

    case Student = 'student';

    case Intern = 'intern';

    case Temporary = 'temporary';

    case SelfEmployed = 'self_employed';

    case Incapacitated = 'incapacitated';

    public function getLabel(): string
    {
        return __('erp.enums.employment_category.'.$this->value);
    }
}
