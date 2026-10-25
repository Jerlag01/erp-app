<?php

namespace App\Filament\Resources\BatteryRooms\Pages;

use App\Filament\Resources\BatteryRooms\BatteryRoomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Battery rooms come with their 609 project; "New" makes such a project by hand.
 */
class ListBatteryRooms extends ListRecords
{
    protected static string $resource = BatteryRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
