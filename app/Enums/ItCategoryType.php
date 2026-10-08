<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ItCategoryType: string implements HasColor, HasLabel
{
    case Asset = 'asset';

    case Accessory = 'accessory';

    case Consumable = 'consumable';

    case Component = 'component';

    case License = 'license';

    public function getLabel(): string
    {
        return __('erp.enums.it_category_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Asset => 'info',
            self::Accessory => 'gray',
            self::Consumable => 'gray',
            self::Component => 'gray',
            self::License => 'gray',
        };
    }
}
