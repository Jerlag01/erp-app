<?php

namespace App\Filament\Resources\ItManufacturers\Pages;

use App\Filament\Resources\ItManufacturers\ItManufacturerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItManufacturers extends ListRecords
{
    protected static string $resource = ItManufacturerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
