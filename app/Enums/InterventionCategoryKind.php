<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Categories (PREPARE, CONNECT, DECONNECT, …) can be combined on one intervention; a subcategory
 * (COLIBRI, …) is chosen once and brings its own phases and tags.
 */
enum InterventionCategoryKind: string implements HasLabel
{
    case Category = 'category';

    case Subcategory = 'subcategory';

    public function getLabel(): string
    {
        return __('erp.enums.intervention_category_kind.'.$this->value);
    }
}
