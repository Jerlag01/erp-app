<?php

namespace App\Filament\Resources\StockItems\Schemas;

use App\Models\StockItem;
use App\Models\StockItemSupplier;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class StockItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 4])
                    ->columnSpanFull()
                    ->schema([
                        Section::make()
                            ->columnSpan(['lg' => 3])
                            ->columns(['default' => 1, 'sm' => 2])
                            ->schema([
                                // The name in the three languages.
                                ...array_map(fn (string $locale): TextEntry => TextEntry::make("name_{$locale}")
                                    ->label(__("erp.stock.names.{$locale}"))
                                    ->placeholder('—'), StockItem::LOCALES),
                                TextEntry::make('category')
                                    ->label(__('erp.fields.category'))
                                    ->state(fn (StockItem $record): ?string => $record->category?->fullName())
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—'),
                                TextEntry::make('unit.name')
                                    ->label(__('erp.fields.unit')),
                                TextEntry::make('low_stock_threshold')
                                    ->label(__('erp.fields.low_stock_threshold'))
                                    ->formatStateUsing(fn (StockItem $record): string => $record->unit->format($record->low_stock_threshold))
                                    ->placeholder(__('erp.stock.no_threshold')),
                                TextEntry::make('manufacturer.name')
                                    ->label(__('erp.resources.manufacturer.singular'))
                                    ->placeholder('—'),
                                TextEntry::make('manufacturer_reference')
                                    ->label(__('erp.fields.manufacturer_reference'))
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('ean')
                                    ->label(__('erp.fields.ean'))
                                    ->fontFamily(FontFamily::Mono)
                                    ->placeholder('—'),
                                // The preferred supplier's price (or the lowest), per supplier below.
                                TextEntry::make('market_price')
                                    ->label(__('erp.fields.market_price'))
                                    ->money('EUR', locale: 'nl_BE')
                                    ->helperText(fn (StockItem $record): ?string => $record->price_updated_at
                                        ? __('erp.stock.price_updated', ['date' => $record->price_updated_at->format('d/m/Y H:i'), 'source' => $record->price_source === StockItemSupplier::MANUAL ? __('erp.stock.suppliers.manual') : $record->price_source])
                                        : null)
                                    ->placeholder('—')
                                    ->visible(fn (): bool => (bool) auth()->user()?->canSeePrices()),
                                TextEntry::make('description')
                                    ->label(__('erp.fields.description'))
                                    ->columnSpanFull()
                                    ->placeholder('—'),
                            ]),
                        Section::make(__('erp.stock.suppliers.title'))
                            ->columnSpan(['lg' => 3])
                            ->schema([
                                RepeatableEntry::make('suppliers')
                                    ->hiddenLabel()
                                    ->placeholder(__('erp.stock.suppliers.none'))
                                    ->table([
                                        TableColumn::make(__('erp.resources.distributor.singular')),
                                        TableColumn::make(__('erp.fields.distributor_reference')),
                                        ...(auth()->user()?->canSeePrices() ? [TableColumn::make(__('erp.fields.market_price'))] : []),
                                        TableColumn::make(''),
                                    ])
                                    ->schema([
                                        TextEntry::make('distributor.name')
                                            ->url(fn (StockItemSupplier $record): ?string => $record->distributor?->store_url, shouldOpenInNewTab: true),
                                        TextEntry::make('reference')
                                            ->fontFamily(FontFamily::Mono)
                                            ->copyable()
                                            ->placeholder('—'),
                                        ...(auth()->user()?->canSeePrices() ? [TextEntry::make('price')
                                            ->money('EUR', locale: 'nl_BE')
                                            ->helperText(fn (StockItemSupplier $record): ?string => $record->price_updated_at
                                                ? __('erp.stock.price_updated', ['date' => $record->price_updated_at->format('d/m/Y H:i'), 'source' => $record->price_source === StockItemSupplier::MANUAL ? __('erp.stock.suppliers.manual') : $record->price_source])
                                                : null)
                                            ->placeholder('—')] : []),
                                        TextEntry::make('is_preferred')
                                            ->formatStateUsing(fn (bool $state): ?string => $state ? __('erp.stock.suppliers.preferred') : null)
                                            ->badge()
                                            ->color('success'),
                                    ]),
                            ]),
                        Grid::make(1)
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                ImageEntry::make('image')
                                    ->hiddenLabel()
                                    ->disk('public')
                                    ->imageHeight(160)
                                    ->visible(fn (StockItem $record): bool => filled($record->image)),
                                ViewEntry::make('qr')
                                    ->hiddenLabel()
                                    ->view('filament.stock.qr'),
                            ]),
                    ]),
            ]);
    }
}
