<?php

namespace App\Filament\Resources\BatteryModels\Pages;

use App\Filament\Resources\BatteryModels\BatteryModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBatteryModels extends ManageRecords
{
    protected static string $resource = BatteryModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
