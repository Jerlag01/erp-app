<?php

namespace App\Filament\Resources\CreditNotes\Pages;

use App\Filament\Resources\CreditNotes\CreditNoteResource;
use App\Filament\Resources\Invoices\Actions\InvoiceActions;
use Filament\Resources\Pages\ViewRecord;

class ViewCreditNote extends ViewRecord
{
    protected static string $resource = CreditNoteResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->number ?? __('erp.finance.invoices.draft_title');
    }

    protected function getHeaderActions(): array
    {
        return InvoiceActions::make();
    }
}
