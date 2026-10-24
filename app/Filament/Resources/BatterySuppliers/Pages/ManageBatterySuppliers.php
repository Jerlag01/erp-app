<?php

namespace App\Filament\Resources\BatterySuppliers\Pages;

use App\Filament\Resources\BatterySuppliers\BatterySupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBatterySuppliers extends ManageRecords
{
    protected static string $resource = BatterySupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
