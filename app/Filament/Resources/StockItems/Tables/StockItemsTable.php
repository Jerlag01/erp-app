<?php

namespace App\Filament\Resources\StockItems\Tables;

use App\Models\StockCategory;
use App\Models\StockItem;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StockItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['unit', 'category.parent', 'manufacturer', 'distributor'])
                ->withSum(['levels as quantity_total' => fn (Builder $query) => $query->visibleTo(auth()->user())], 'quantity')
                ->withExists(['levels as is_low' => fn (Builder $query) => $query->visibleTo(auth()->user())->low()]))
            ->defaultSort('name')
            ->groups([
                Group::make('category.name')
                    ->label(__('erp.fields.subcategory'))
                    ->getTitleFromRecordUsing(fn (StockItem $record): string => $record->category?->fullName() ?? __('erp.stock.uncategorised')),
                Group::make('category.parent.name')
                    ->label(__('erp.fields.category'))
                    ->getTitleFromRecordUsing(fn (StockItem $record): string => $record->category?->parent?->name ?? $record->category?->name ?? __('erp.stock.uncategorised')),
            ])
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(40)
                    ->toggleable(),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (StockItem $record): ?string => $record->manufacturer?->name)
                    ->weight('bold')
                    ->wrap()
                    ->searchable(['name', 'manufacturer_reference', 'distributor_reference', 'ean'])
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('erp.fields.category'))
                    ->state(fn (StockItem $record): ?string => $record->category?->fullName())
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('manufacturer_reference')
                    ->label(__('erp.fields.manufacturer_reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('distributor_reference')
                    ->label(__('erp.fields.distributor_reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (StockItem $record): ?string => $record->distributor?->name)
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('quantity_total')
                    ->label(__('erp.fields.quantity'))
                    ->formatStateUsing(fn (StockItem $record): string => $record->unit->format($record->quantity_total))
                    ->badge()
                    ->color(fn (StockItem $record): string => $record->is_low ? 'danger' : ((float) $record->quantity_total > 0 ? 'success' : 'gray'))
                    ->tooltip(fn (StockItem $record): ?string => $record->is_low ? __('erp.stock.low') : null)
                    ->sortable(),
                TextColumn::make('market_price')
                    ->label(__('erp.fields.market_price'))
                    ->money('EUR', locale: 'nl_BE')
                    ->description(fn (StockItem $record): ?string => '/ '.$record->unit->abbreviation)
                    ->placeholder('—')
                    ->sortable()
                    ->visible(fn (): bool => (bool) auth()->user()?->canSeePrices()),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('erp.fields.category'))
                    ->options(fn (): array => StockCategory::query()->topLevel()->orderBy('sort')->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, $id) => $query->inCategory((int) $id))),
                SelectFilter::make('subcategory')
                    ->label(__('erp.fields.subcategory'))
                    ->options(fn (): array => StockCategory::groupedOptions())
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, $id) => $query->where('stock_category_id', $id))),
                SelectFilter::make('manufacturer')
                    ->label(__('erp.resources.manufacturer.singular'))
                    ->relationship('manufacturer', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('distributor')
                    ->label(__('erp.resources.distributor.singular'))
                    ->relationship('distributor', 'name')
                    ->preload(),
                Filter::make('low')
                    ->label(__('erp.stock.low'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('levels', fn (Builder $query) => $query->visibleTo(auth()->user())->low())),
                TernaryFilter::make('is_active')
                    ->label(__('erp.fields.is_active'))
                    ->default(true),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('printLabels')
                        ->label(__('erp.stock.print_labels'))
                        ->icon(Heroicon::OutlinedQrCode)
                        ->action(fn (Collection $records, $livewire) => $livewire->js('window.open('.json_encode(route('stock.labels', ['items' => $records->modelKeys()])).', "_blank")'))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
