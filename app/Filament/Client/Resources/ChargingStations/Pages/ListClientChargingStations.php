<?php

namespace App\Filament\Client\Resources\ChargingStations\Pages;

use App\Filament\Client\Resources\ChargingStations\ClientChargingStationResource;
use Filament\Resources\Pages\ListRecords;

class ListClientChargingStations extends ListRecords
{
    protected static string $resource = ClientChargingStationResource::class;
}
