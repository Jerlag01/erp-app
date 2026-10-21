<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Where a 608 project or a charging station is: at a COW site (chosen from the list), or at a
 * third-party client with its own installation address.
 */
enum SiteLocationType: string implements HasIcon, HasLabel
{
    case Cow = 'cow';

    case Other = 'other';

    public function getLabel(): string
    {
        return __('erp.enums.site_location_type.'.$this->value);
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Cow => Heroicon::OutlinedBuildingOffice2,
            self::Other => Heroicon::OutlinedMapPin,
        };
    }
}
