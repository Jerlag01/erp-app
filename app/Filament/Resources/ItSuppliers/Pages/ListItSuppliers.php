<?php

namespace App\Filament\Resources\ItSuppliers\Pages;

use App\Filament\Resources\ItSuppliers\ItSupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItSuppliers extends ListRecords
{
    protected static string $resource = ItSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
