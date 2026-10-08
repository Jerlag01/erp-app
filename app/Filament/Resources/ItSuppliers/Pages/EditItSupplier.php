<?php

namespace App\Filament\Resources\ItSuppliers\Pages;

use App\Filament\Resources\ItSuppliers\ItSupplierResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItSupplier extends EditRecord
{
    protected static string $resource = ItSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
