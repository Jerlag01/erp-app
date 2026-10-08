<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The three Belgian regions; battery waste is reported per region.
 */
enum WasteRegion: string implements HasLabel
{
    case Flanders = 'flanders';

    case Brussels = 'brussels';

    case Wallonia = 'wallonia';

    public function getLabel(): string
    {
        return __('erp.enums.waste_region.'.$this->value);
    }
}
