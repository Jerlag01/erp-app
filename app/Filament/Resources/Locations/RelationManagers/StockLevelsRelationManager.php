<?php

namespace App\Filament\Resources\Locations\RelationManagers;

use App\Filament\Resources\StockItems\Actions\StockLevelActions;
use App\Filament\Resources\StockItems\StockItemResource;
use App\Filament\Resources\StockItems\Tables\ThresholdColumn;
use App\Models\Location;
use App\Models\StockItem;
use App\Models\StockLevel;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The stock present at this location, item by item.
 */
class StockLevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockLevels';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.stock.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Location $ownerRecord */
        return modules()->stock()
            && (bool) auth()->user()?->hasPermission('stock_items.view')
            && StockLevel::query()->visibleTo(auth()->user())->where('location_id', $ownerRecord->id)->exists();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['item.unit', 'item.category.parent']))
            ->recordUrl(fn (StockLevel $record): string => StockItemResource::getUrl('view', ['record' => $record->stock_item_id]))
            ->defaultSort(fn (Builder $query) => $query->orderBy(
                StockItem::query()->select('name')->whereColumn('stock_items.id', 'stock_levels.stock_item_id')
            ))
            ->columns([
                TextColumn::make('item.name')
                    ->label(__('erp.resources.stock_item.singular'))
                    ->description(fn (StockLevel $record): ?string => $record->item->category?->fullName())
                    ->searchable(),
                TextColumn::make('item.manufacturer_reference')
                    ->label(__('erp.fields.manufacturer_reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label(__('erp.fields.quantity'))
                    ->formatStateUsing(fn (StockLevel $record): string => $record->item->unit->format($record->quantity))
                    ->badge()
                    ->color(fn (StockLevel $record): string => $record->isLow() ? 'danger' : ((float) $record->quantity > 0 ? 'success' : 'gray'))
                    ->sortable(),
                ThresholdColumn::make(),
            ])
            ->filters([
                Filter::make('in_stock')
                    ->label(__('erp.stock.only_in_stock'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('quantity', '>', 0)),
                Filter::make('low')
                    ->label(__('erp.stock.low'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->low()),
            ])
            ->recordActions(StockLevelActions::make());
    }
}
