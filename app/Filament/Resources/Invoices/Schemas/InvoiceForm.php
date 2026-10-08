<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceType;
use App\Enums\VatRate;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Project;
use App\Models\StandardInvoiceLine;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * The form of an invoice or credit note. New invoices start with the standard lines; a credit
 * note's copied content is read-only until someone with "credit_notes.override" switches
 * "allow changes" on.
 */
class InvoiceForm
{
    public static function configure(Schema $schema, InvoiceType $type): Schema
    {
        $isCredit = $type === InvoiceType::CreditNote;
        // Credit notes: locked unless changes are allowed (by someone with the override right).
        $locked = fn (Get $get, ?Invoice $record): bool => $isCredit && ! ($get('allow_changes') && $record && Gate::allows('overrideCopiedContent', $record));

        return $schema
            ->columns(3)
            ->components([
                Section::make(__('erp.finance.invoices.credited'))
                    ->icon(Heroicon::OutlinedReceiptRefund)
                    ->columnSpanFull()
                    ->visible($isCredit)
                    ->schema([
                        Text::make(fn (?Invoice $record): string => $record?->creditedInvoice
                            ? __('erp.finance.invoices.credits_invoice', ['number' => $record->creditedInvoice->number, 'date' => $record->creditedInvoice->issue_date->format('d/m/Y'), 'amount' => Invoice::money($record->creditedInvoice->total)])
                            : '—'),
                        Toggle::make('allow_changes')
                            ->label(__('erp.finance.invoices.allow_changes'))
                            ->helperText(__('erp.finance.invoices.allow_changes_help'))
                            ->live()
                            ->visible(fn (?Invoice $record): bool => $record !== null && Gate::allows('overrideCopiedContent', $record)),
                    ]),
                Section::make()
                    ->columns(2)
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        Select::make('client_id')
                            ->label(__('erp.resources.client.singular'))
                            ->options(fn (?Invoice $record): array => Client::query()
                                ->where(fn (Builder $query) => $query->where('is_active', true)->when($record, fn (Builder $query) => $query->orWhere('id', $record->client_id)))
                                ->orderBy('short_name')->get()->mapWithKeys(fn (Client $client): array => [$client->id => $client->label()])->all())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => self::applyClientVatRate($state, $get, $set))
                            ->disabled($locked)
                            ->columnSpanFull(),
                        Select::make('project_id')
                            ->label(__('erp.resources.project.singular'))
                            ->options(fn (): array => Project::query()->with('category')->latest()->limit(500)->get()
                                ->mapWithKeys(fn (Project $project): array => [$project->id => trim($project->reference.' · '.$project->subtitle(), ' ·')])->all())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('poNumbers', []))
                            ->visible(fn (): bool => modules()->projects())
                            ->disabled($locked),
                        // Only the project's PO numbers that no other invoice billed yet.
                        Select::make('poNumbers')
                            ->label(__('erp.projects.po_numbers'))
                            ->helperText(__('erp.finance.invoices.po_help'))
                            ->relationship('poNumbers', 'number', fn (Builder $query, Get $get, ?Invoice $record) => $query
                                ->where('project_id', $get('project_id') ?: 0)
                                ->whereDoesntHave('invoices', fn (Builder $query) => $query->when($record, fn (Builder $query) => $query->whereKeyNot($record->id))))
                            ->multiple()
                            ->preload()
                            ->visible(fn (Get $get): bool => ! $isCredit && modules()->projects() && filled($get('project_id'))),
                        DatePicker::make('issue_date')
                            ->label(__('erp.finance.invoices.issue_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->required(),
                        DatePicker::make('due_date')
                            ->label(__('erp.finance.invoices.due_date'))
                            ->helperText(__('erp.finance.invoices.due_date_help'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('issue_date')
                            ->visible(! $isCredit),
                        TextInput::make('subject')
                            ->label(__('erp.finance.invoices.subject'))
                            ->maxLength(255)
                            ->disabled($locked)
                            ->columnSpanFull(),
                        TextInput::make('client_reference')
                            ->label(__('erp.finance.invoices.client_reference'))
                            ->helperText(__('erp.finance.invoices.client_reference_help'))
                            ->maxLength(255)
                            ->disabled($locked),
                    ]),
                Section::make(__('erp.finance.invoices.totals'))
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Text::make(fn (Get $get): string => self::totalsText((array) $get('lines'))),
                    ]),
                Repeater::make('lines')
                    ->label(__('erp.finance.invoices.lines'))
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->default(fn (): array => $isCredit ? [] : self::standardLines())
                    ->addActionLabel(__('erp.finance.invoices.add_line'))
                    ->columns(12)
                    ->reorderableWithButtons()
                    ->live(debounce: 500)
                    ->disabled($locked)
                    ->schema([
                        TextInput::make('description')
                            ->label(__('erp.finance.pdf.description'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['default' => 12, 'md' => 5]),
                        TextInput::make('quantity')
                            ->label(__('erp.finance.pdf.quantity'))
                            ->numeric()
                            ->default(1)
                            ->step('any')
                            ->required()
                            ->columnSpan(['default' => 4, 'md' => 1]),
                        TextInput::make('unit')
                            ->label(__('erp.finance.invoices.unit'))
                            ->placeholder(__('erp.finance.invoices.unit_placeholder'))
                            ->maxLength(20)
                            ->columnSpan(['default' => 4, 'md' => 1]),
                        TextInput::make('unit_price')
                            ->label(__('erp.finance.pdf.unit_price'))
                            ->numeric()
                            ->step(0.01)
                            ->prefix('€')
                            ->required()
                            ->columnSpan(['default' => 4, 'md' => 2]),
                        Select::make('vat_rate')
                            ->label(__('erp.finance.pdf.vat_rate'))
                            ->options(VatRate::class)
                            ->required()
                            ->columnSpan(['default' => 6, 'md' => 2]),
                        Text::make(fn (Get $get): string => Invoice::money(InvoiceLine::totalFor($get('quantity'), $get('unit_price'))))
                            ->columnSpan(['default' => 6, 'md' => 1]),
                    ])
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label(__('erp.finance.invoices.notes'))
                    ->helperText(__('erp.finance.invoices.notes_help'))
                    ->rows(2)
                    ->maxLength(2000)
                    ->disabled($locked)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The active standard lines (file costs, BTV inspection, …) a new invoice starts with.
     *
     * @return list<array<string, mixed>>
     */
    public static function standardLines(): array
    {
        return StandardInvoiceLine::query()->where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn (StandardInvoiceLine $line): array => [
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => (float) $line->unit_price,
                'vat_rate' => $line->vat_rate?->value,
                'is_standard' => true,
            ])->all();
    }

    /**
     * Lines without a VAT rate take the chosen client's default rate.
     */
    protected static function applyClientVatRate(mixed $clientId, Get $get, Set $set): void
    {
        $rate = Client::find($clientId)?->default_vat_rate?->value;

        if ($rate === null) {
            return;
        }

        $lines = (array) $get('lines');

        foreach ($lines as $key => $line) {
            if (blank($line['vat_rate'] ?? null)) {
                $lines[$key]['vat_rate'] = $rate;
            }
        }

        $set('lines', $lines);
    }

    /**
     * @param  array<array-key, array<string, mixed>>  $lines
     */
    public static function totalsText(array $lines): string
    {
        $bases = [];

        foreach ($lines as $line) {
            $rate = VatRate::tryFrom((string) ($line['vat_rate'] instanceof VatRate ? $line['vat_rate']->value : ($line['vat_rate'] ?? ''))) ?? VatRate::TwentyOne;
            $bases[$rate->value] = ($bases[$rate->value] ?? 0) + InvoiceLine::totalFor($line['quantity'] ?? 0, $line['unit_price'] ?? 0);
        }

        $subtotal = array_sum($bases);
        $vat = array_sum(array_map(fn (string $rate, float $base): float => round($base * VatRate::from($rate)->percentage() / 100, 2), array_keys($bases), $bases));

        return __('erp.finance.invoices.totals_text', ['subtotal' => Invoice::money($subtotal), 'vat' => Invoice::money($vat), 'total' => Invoice::money($subtotal + $vat)]);
    }
}
