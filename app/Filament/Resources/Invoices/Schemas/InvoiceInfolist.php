<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Invoice;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Illuminate\Support\Facades\Gate;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make()
                            ->columnSpan(['lg' => 2])
                            ->columns(3)
                            ->schema([
                                TextEntry::make('client.company_name')
                                    ->label(__('erp.resources.client.singular'))
                                    ->helperText(fn (Invoice $record): string => implode(', ', $record->client->addressLines()))
                                    ->columnSpan(2),
                                TextEntry::make('client.vat_number')->label(__('erp.finance.clients.vat_number'))->fontFamily(FontFamily::Mono)->placeholder('—'),
                                TextEntry::make('issue_date')->label(__('erp.finance.invoices.issue_date'))->date('d/m/Y'),
                                TextEntry::make('due_date')
                                    ->label(__('erp.finance.invoices.due_date'))
                                    ->date('d/m/Y')
                                    ->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null)
                                    ->placeholder(fn (Invoice $record): string => __('erp.finance.invoices.due_after', ['days' => $record->client->payment_term_days]))
                                    ->hidden(fn (Invoice $record): bool => $record->isCreditNote()),
                                TextEntry::make('creditedInvoice.number')
                                    ->label(__('erp.finance.pdf.credits'))
                                    ->fontFamily(FontFamily::Mono)
                                    ->visible(fn (Invoice $record): bool => $record->isCreditNote()),
                                TextEntry::make('project.reference')
                                    ->label(__('erp.resources.project.singular'))
                                    ->fontFamily(FontFamily::Mono)
                                    ->url(fn (Invoice $record): ?string => $record->project && Gate::allows('view', $record->project) ? ProjectResource::getUrl('view', ['record' => $record->project]) : null)
                                    ->placeholder('—'),
                                TextEntry::make('po')
                                    ->label(__('erp.projects.po_numbers'))
                                    ->state(fn (Invoice $record): array => $record->poNumberList())
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—'),
                                TextEntry::make('client_reference')->label(__('erp.finance.invoices.client_reference'))->placeholder('—'),
                                TextEntry::make('subject')->label(__('erp.finance.invoices.subject'))->placeholder('—')->columnSpanFull(),
                            ]),
                        Section::make()
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                TextEntry::make('status')->label(__('erp.fields.status'))->badge(),
                                TextEntry::make('total')
                                    ->label(__('erp.finance.incl_vat'))
                                    ->state(fn (Invoice $record): string => Invoice::money($record->total))
                                    ->helperText(fn (Invoice $record): string => __('erp.finance.excl_vat').': '.Invoice::money($record->subtotal))
                                    ->weight('bold')
                                    ->size('lg'),
                                TextEntry::make('structured_reference')
                                    ->label(__('erp.finance.pdf.structured'))
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->visible(fn (Invoice $record): bool => filled($record->structured_reference) && ! $record->isCreditNote()),
                                TextEntry::make('sent_at')
                                    ->label(__('erp.finance.invoices.sent'))
                                    ->state(fn (Invoice $record): ?string => $record->sent_at ? $record->sent_at->format('d/m/Y H:i').' · '.$record->sent_to : null)
                                    ->helperText(fn (Invoice $record): ?string => $record->peppol_status ? 'Peppol: '.__("erp.finance.peppol.{$record->peppol_status}") : null)
                                    ->placeholder('—'),
                                TextEntry::make('paid_on')->label(__('erp.finance.invoices.paid_on'))->date('d/m/Y')->placeholder('—')->hidden(fn (Invoice $record): bool => $record->isCreditNote()),
                                TextEntry::make('reminders')
                                    ->label(__('erp.finance.invoices.reminders'))
                                    ->state(fn (Invoice $record): array => $record->reminders->map(fn ($reminder): string => __('erp.finance.invoices.reminder_line', ['level' => $reminder->level, 'date' => $reminder->sent_at->format('d/m/Y')]))->all())
                                    ->listWithLineBreaks()
                                    ->visible(fn (Invoice $record): bool => $record->reminders->isNotEmpty()),
                            ]),
                    ]),
                Section::make(__('erp.finance.invoices.lines'))
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('lines')->hiddenLabel()->view('finance.invoice-lines'),
                        TextEntry::make('notes')->label(__('erp.finance.invoices.notes'))->visible(fn (Invoice $record): bool => filled($record->notes)),
                    ]),
            ]);
    }
}
