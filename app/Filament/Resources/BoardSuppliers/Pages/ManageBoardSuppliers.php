<?php

namespace App\Filament\Resources\BoardSuppliers\Pages;

use App\Filament\Resources\BoardSuppliers\BoardSupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBoardSuppliers extends ManageRecords
{
    protected static string $resource = BoardSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
