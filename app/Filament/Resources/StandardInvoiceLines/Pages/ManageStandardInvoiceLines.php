<?php

namespace App\Filament\Resources\StandardInvoiceLines\Pages;

use App\Filament\Resources\StandardInvoiceLines\StandardInvoiceLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStandardInvoiceLines extends ManageRecords
{
    protected static string $resource = StandardInvoiceLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
