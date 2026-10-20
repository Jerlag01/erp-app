<?php

namespace App\Filament\Client\Resources\BatteryDeliveries\Pages;

use App\Filament\Client\Resources\BatteryDeliveries\ClientBatteryDeliveryResource;
use Filament\Resources\Pages\ListRecords;

class ListClientBatteryDeliveries extends ListRecords
{
    protected static string $resource = ClientBatteryDeliveryResource::class;
}
