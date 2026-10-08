<?php

namespace App\Filament\Resources\StockItems\RelationManagers;

use App\Filament\Resources\StockItems\Actions\StockLevelActions;
use App\Filament\Resources\StockItems\Tables\ThresholdColumn;
use App\Models\Location;
use App\Models\StockItem;
use App\Models\StockLevel;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Stock of this item per location, with the + and − buttons. Employees only see their own locations.
 */
class LevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'levels';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedMapPin;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.stock.per_location');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var StockItem $item */
        $item = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->visibleTo(auth()->user())->with(['location', 'item.unit']))
            ->defaultSort(fn (Builder $query) => $query->orderBy(Location::query()->select('name')->whereColumn('locations.id', 'stock_levels.location_id')))
            ->paginated(false)
            ->emptyStateHeading(__('erp.stock.no_locations'))
            ->emptyStateDescription(__('erp.stock.no_locations_help'))
            ->columns([
                TextColumn::make('location.name')
                    ->label(__('erp.resources.location.singular'))
                    ->weight('bold'),
                TextColumn::make('quantity')
                    ->label(__('erp.fields.quantity'))
                    ->formatStateUsing(fn (StockLevel $record): string => $item->unit->format($record->quantity))
                    ->badge()
                    ->size('lg')
                    ->color(fn (StockLevel $record): string => $record->isLow() ? 'danger' : ((float) $record->quantity > 0 ? 'success' : 'gray')),
                ThresholdColumn::make(),
                TextColumn::make('value')
                    ->label(__('erp.stock.value'))
                    ->state(fn (StockLevel $record): ?float => $item->market_price !== null ? (float) $record->quantity * (float) $item->market_price : null)
                    ->money('EUR', locale: 'nl_BE')
                    ->placeholder('—')
                    ->toggleable()
                    ->visible(fn (): bool => (bool) auth()->user()?->canSeePrices()),
            ])
            ->recordActions(StockLevelActions::make());
    }
}
