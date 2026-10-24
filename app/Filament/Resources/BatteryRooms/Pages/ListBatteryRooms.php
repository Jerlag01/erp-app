<?php

namespace App\Filament\Resources\BatteryRooms\Pages;

use App\Filament\Resources\BatteryRooms\BatteryRoomResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Battery rooms come with their 609 project, so there is no "new" button here.
 */
class ListBatteryRooms extends ListRecords
{
    protected static string $resource = BatteryRoomResource::class;
}
