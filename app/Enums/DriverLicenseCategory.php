<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Categories of the European (Belgian) driver license. An employee can hold several.
 */
enum DriverLicenseCategory: string implements HasDescription, HasLabel
{
    case AM = 'AM';

    case A1 = 'A1';

    case A2 = 'A2';

    case A = 'A';

    case B = 'B';

    case BE = 'BE';

    case C1 = 'C1';

    case C1E = 'C1E';

    case C = 'C';

    case CE = 'CE';

    case D1 = 'D1';

    case D1E = 'D1E';

    case D = 'D';

    case DE = 'DE';

    case G = 'G';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getDescription(): string
    {
        return __('erp.enums.driver_license_category.'.$this->value);
    }
}
