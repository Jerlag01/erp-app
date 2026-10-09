<?php

namespace App\Filament\Resources\BatteryDossiers\Pages;

use App\Filament\Resources\BatteryDossiers\BatteryDossierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBatteryDossiers extends ListRecords
{
    protected static string $resource = BatteryDossierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
