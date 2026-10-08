<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceType;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'type' => InvoiceType::Invoice, 'created_by' => auth()->id()];
    }

    protected function afterCreate(): void
    {
        $this->getRecord()->recalculate();
    }

    protected function getRedirectUrl(): string
    {
        return InvoiceResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
