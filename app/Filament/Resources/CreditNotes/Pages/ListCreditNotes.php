<?php

namespace App\Filament\Resources\CreditNotes\Pages;

use App\Actions\Finance\CreateCreditNote;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Resources\CreditNotes\CreditNoteResource;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCreditNotes extends ListRecords
{
    protected static string $resource = CreditNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A credit note always starts from the invoice it credits.
            Action::make('create')
                ->label(__('erp.finance.invoices.create_credit_note'))
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => InvoicePolicy::allows(auth()->user(), InvoiceType::CreditNote, 'create'))
                ->authorize(fn (): bool => InvoicePolicy::allows(auth()->user(), InvoiceType::CreditNote, 'create'))
                ->schema([
                    Select::make('invoice_id')
                        ->label(__('erp.finance.invoices.credit_which'))
                        ->options(fn (): array => Invoice::query()
                            ->where('type', InvoiceType::Invoice)
                            ->where('status', '!=', InvoiceStatus::Draft)
                            ->with('client')
                            ->latest('issue_date')
                            ->limit(500)
                            ->get()
                            ->mapWithKeys(fn (Invoice $invoice): array => [$invoice->id => "{$invoice->number} · {$invoice->client->short_name} · ".Invoice::money($invoice->total)])
                            ->all())
                        ->searchable()
                        ->required(),
                ])
                ->action(fn (array $data, CreateCreditNote $create) => redirect(CreditNoteResource::getUrl('edit', [
                    'record' => $create->handle(Invoice::query()->where('type', InvoiceType::Invoice)->findOrFail($data['invoice_id']), auth()->user()),
                ]))),
        ];
    }
}
