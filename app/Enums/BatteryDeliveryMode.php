<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * "Afvoer/aanvoer" of the new batteries: we place them (Ja), they are delivered (Levering PI) or
 * they are already on site (Nee). It decides the first line of the delivery note and invoice request.
 */
enum BatteryDeliveryMode: string implements HasLabel
{
    case Place = 'ja';

    case Delivery = 'pi';

    case OnSite = 'nee';

    public function getLabel(): string
    {
        return __('erp.batteries.delivery_modes.'.$this->value);
    }
}
