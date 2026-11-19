<?php

namespace App\Filament\Resources\VatCodes\Pages;

use App\Filament\Resources\VatCodes\VatCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVatCodes extends ManageRecords
{
    protected static string $resource = VatCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->slideOver(),
        ];
    }
}
