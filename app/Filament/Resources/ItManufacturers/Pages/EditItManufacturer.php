<?php

namespace App\Filament\Resources\ItManufacturers\Pages;

use App\Filament\Resources\ItManufacturers\ItManufacturerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItManufacturer extends EditRecord
{
    protected static string $resource = ItManufacturerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
