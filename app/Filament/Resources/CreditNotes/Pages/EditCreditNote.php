<?php

namespace App\Filament\Resources\CreditNotes\Pages;

use App\Filament\Resources\CreditNotes\CreditNoteResource;
use App\Models\Invoice;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditCreditNote extends EditRecord
{
    protected static string $resource = CreditNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * Without the override right, "allow changes" cannot be switched on through a crafted request.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Invoice $record */
        $record = $this->getRecord();

        if (! Gate::allows('overrideCopiedContent', $record)) {
            unset($data['allow_changes']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->getRecord()->recalculate();
    }

    protected function getRedirectUrl(): string
    {
        return CreditNoteResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
