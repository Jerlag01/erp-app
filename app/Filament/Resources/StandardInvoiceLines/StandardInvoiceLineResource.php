<?php

namespace App\Filament\Resources\StandardInvoiceLines;

use App\Enums\NavigationGroup;
use App\Enums\VatRate;
use App\Filament\Resources\StandardInvoiceLines\Pages\ManageStandardInvoiceLines;
use App\Models\StandardInvoiceLine;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The lines every new invoice starts with (file costs, BTV inspection, …), added, changed and
 * removed freely.
 */
class StandardInvoiceLineResource extends Resource
{
    protected static ?string $model = StandardInvoiceLine::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'standard-invoice-lines';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 7;

    public static function getModelLabel(): string
    {
        return __('erp.resources.standard_line.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.standard_line.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('description')->label(__('erp.finance.pdf.description'))->required()->maxLength(255)->columnSpanFull(),
                TextInput::make('quantity')->label(__('erp.finance.pdf.quantity'))->numeric()->default(1)->required(),
                TextInput::make('unit')->label(__('erp.finance.invoices.unit'))->maxLength(20),
                TextInput::make('unit_price')->label(__('erp.finance.pdf.unit_price'))->numeric()->step(0.01)->prefix('€')->default(0)->required(),
                Select::make('vat_rate')->label(__('erp.finance.pdf.vat_rate'))->options(VatRate::class)->placeholder(__('erp.finance.standard_lines.client_rate')),
                Toggle::make('is_active')->label(__('erp.finance.standard_lines.is_active'))->helperText(__('erp.finance.standard_lines.is_active_help'))->default(true),
                TextInput::make('sort_order')->label(__('erp.kb.sort_order'))->integer()->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('description')->label(__('erp.finance.pdf.description'))->weight('medium'),
                TextColumn::make('quantity')->label(__('erp.finance.pdf.quantity'))->formatStateUsing(fn (StandardInvoiceLine $record): string => rtrim(rtrim((string) $record->quantity, '0'), '.').' '.$record->unit),
                TextColumn::make('unit_price')->label(__('erp.finance.pdf.unit_price'))->money('EUR', locale: 'nl_BE'),
                TextColumn::make('vat_rate')->label(__('erp.finance.pdf.vat_rate'))->placeholder(__('erp.finance.standard_lines.client_rate')),
                IconColumn::make('is_active')->label(__('erp.finance.standard_lines.is_active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStandardInvoiceLines::route('/'),
        ];
    }
}
