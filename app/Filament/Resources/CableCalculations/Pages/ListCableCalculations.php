<?php

namespace App\Filament\Resources\CableCalculations\Pages;

use App\Filament\Resources\CableCalculations\CableCalculationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCableCalculations extends ListRecords
{
    protected static string $resource = CableCalculationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
