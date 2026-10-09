<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Material on an intervention is used (installed), or freed when a category such as DECONNECT
 * removes it.
 */
enum MaterialDirection: string implements HasLabel
{
    case Used = 'used';

    case Freed = 'freed';

    public function getLabel(): string
    {
        return __('erp.enums.material_direction.'.$this->value);
    }
}
