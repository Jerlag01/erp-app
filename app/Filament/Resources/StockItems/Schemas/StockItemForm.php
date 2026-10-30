<?php

namespace App\Filament\Resources\StockItems\Schemas;

use App\Models\Distributor;
use App\Models\StockCategory;
use App\Models\StockItemSupplier;
use App\Models\Unit;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class StockItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('erp.sections.stock_item'))
                            ->columnSpan(['lg' => 2])
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('erp.fields.name'))
                                    ->placeholder(__('erp.help.stock_item_name'))
                                    ->required()
                                    ->maxLength(200)
                                    ->columnSpanFull(),
                                Select::make('stock_category_id')
                                    ->label(__('erp.fields.category'))
                                    ->options(fn (): array => StockCategory::groupedOptions())
                                    ->searchable(),
                                Select::make('unit_id')
                                    ->label(__('erp.fields.unit'))
                                    ->relationship('unit', 'name')
                                    ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->name} ({$record->abbreviation})")
                                    ->preload()
                                    ->required()
                                    ->live(),
                                Select::make('manufacturer_id')
                                    ->label(__('erp.resources.manufacturer.singular'))
                                    ->relationship('manufacturer', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                TextInput::make('manufacturer_reference')
                                    ->label(__('erp.fields.manufacturer_reference'))
                                    ->maxLength(100)
                                    ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                                TextInput::make('ean')
                                    ->label(__('erp.fields.ean'))
                                    ->maxLength(20)
                                    ->regex('/^\d{8,14}$/')
                                    ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                                TextInput::make('low_stock_threshold')
                                    ->label(__('erp.fields.low_stock_threshold'))
                                    ->helperText(__('erp.help.low_stock_threshold'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): ?string => Unit::find($get('unit_id'))?->abbreviation),
                                Textarea::make('description')
                                    ->label(__('erp.fields.description'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Section::make(__('erp.fields.image'))
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                FileUpload::make('image')
                                    ->hiddenLabel()
                                    ->helperText(__('erp.help.stock_image'))
                                    ->image()
                                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                    ->maxSize(4096)
                                    ->disk('public')
                                    ->directory('stock-items')
                                    ->visibility('public')
                                    ->imageEditor(),
                                Toggle::make('is_active')
                                    ->label(__('erp.fields.is_active'))
                                    ->helperText(__('erp.help.stock_active'))
                                    ->default(true),
                                Textarea::make('notes')
                                    ->label(__('erp.fields.notes'))
                                    ->rows(4),
                            ]),
                        // Several suppliers, each with its own article number and price.
                        Section::make(__('erp.stock.suppliers.title'))
                            ->description(__('erp.stock.suppliers.help'))
                            ->columnSpan(['lg' => 2])
                            ->schema([
                                Repeater::make('suppliers')
                                    ->hiddenLabel()
                                    ->relationship('suppliers')
                                    ->reorderable(false)
                                    ->columns(['default' => 1, 'md' => 4])
                                    ->addActionLabel(__('erp.stock.suppliers.add'))
                                    ->defaultItems(0)
                                    ->itemLabel(fn (array $state): ?string => Distributor::query()->find($state['distributor_id'] ?? null)?->name)
                                    ->schema([
                                        Select::make('distributor_id')
                                            ->label(__('erp.resources.distributor.singular'))
                                            ->helperText(fn (Get $get): ?string => $get('../../manufacturer_id') ? __('erp.help.distributor_filtered') : null)
                                            ->options(fn (Get $get): array => Distributor::query()
                                                ->when(
                                                    $get('../../manufacturer_id') && Distributor::whereHas('manufacturers', fn (Builder $query) => $query->whereKey($get('../../manufacturer_id')))->exists(),
                                                    fn (Builder $query) => $query->whereHas('manufacturers', fn (Builder $query) => $query->whereKey($get('../../manufacturer_id'))),
                                                )
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all())
                                            ->searchable()
                                            ->required()
                                            ->distinct()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->live(),
                                        TextInput::make('reference')
                                            ->label(__('erp.fields.distributor_reference'))
                                            ->maxLength(100)
                                            ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                                        TextInput::make('price')
                                            ->label(__('erp.fields.market_price'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('€')
                                            // With a connection the price is fetched; disabled fields are not saved, so it stays.
                                            ->disabled(fn (Get $get): bool => (bool) Distributor::query()->find($get('distributor_id'))?->hasPriceConnection())
                                            ->helperText(fn (Get $get, ?StockItemSupplier $record): ?string => match (true) {
                                                (bool) Distributor::query()->find($get('distributor_id'))?->hasPriceConnection() => __('erp.stock.suppliers.price_fetched'),
                                                $record?->price_updated_at !== null => __('erp.stock.suppliers.price_manual', ['date' => $record->price_updated_at->format('d/m/Y')]),
                                                default => __('erp.stock.suppliers.price_manual_help'),
                                            })
                                            // Hidden fields are not saved, so the stored price stays as it is.
                                            ->visible(fn (): bool => (bool) auth()->user()?->canSeePrices()),
                                        Toggle::make('is_preferred')
                                            ->label(__('erp.stock.suppliers.preferred'))
                                            ->helperText(__('erp.stock.suppliers.preferred_help'))
                                            ->inline(false),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
