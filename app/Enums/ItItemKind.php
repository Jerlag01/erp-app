<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ItItemKind: string implements HasColor, HasLabel
{
    case Accessory = 'accessory';

    case Consumable = 'consumable';

    case Component = 'component';

    public function getLabel(): string
    {
        return __('erp.enums.it_item_kind.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Accessory => 'info',
            self::Consumable => 'warning',
            self::Component => 'gray',
        };
    }
}
