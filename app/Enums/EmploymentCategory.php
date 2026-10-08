<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Belgian employment status categories.
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

    public function getLabel(): string
    {
        return __('erp.enums.employment_category.'.$this->value);
    }
}
