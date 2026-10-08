<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Filament\Resources\StockItems\Tables\ThresholdColumn;
use App\Models\StockLevel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stock rows below their minimum, at the locations the user may see.
 */
class LowStock extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return modules()->stock() && (bool) auth()->user()?->hasPermission('stock_items.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('erp.stock.low'))
            ->query(fn (): Builder => StockLevel::query()->visibleTo(auth()->user())->low()->with(['item.unit', 'location']))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (StockLevel $record): string => StockItemResource::getUrl('view', ['record' => $record->stock_item_id]))
            ->emptyStateHeading(__('erp.stock.nothing_low'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('item.name')
                    ->label(__('erp.resources.stock_item.singular'))
                    ->weight('bold'),
                TextColumn::make('location.name')
                    ->label(__('erp.resources.location.singular')),
                TextColumn::make('quantity')
                    ->label(__('erp.fields.quantity'))
                    ->formatStateUsing(fn (StockLevel $record): string => $record->item->unit->format($record->quantity))
                    ->badge()
                    ->color('danger'),
                ThresholdColumn::make(),
            ]);
    }
}
