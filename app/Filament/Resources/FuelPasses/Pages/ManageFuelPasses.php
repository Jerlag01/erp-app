<?php

namespace App\Filament\Resources\FuelPasses\Pages;

use App\Filament\Resources\FuelPasses\FuelPassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFuelPasses extends ManageRecords
{
    protected static string $resource = FuelPassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
