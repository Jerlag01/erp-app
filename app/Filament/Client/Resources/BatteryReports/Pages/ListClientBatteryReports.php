<?php

namespace App\Filament\Client\Resources\BatteryReports\Pages;

use App\Filament\Client\Resources\BatteryReports\ClientBatteryReportResource;
use Filament\Resources\Pages\ListRecords;

class ListClientBatteryReports extends ListRecords
{
    protected static string $resource = ClientBatteryReportResource::class;
}
