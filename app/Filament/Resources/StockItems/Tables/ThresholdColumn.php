<?php

namespace App\Filament\Resources\StockItems\Tables;

use App\Models\StockLevel;
use Filament\Tables\Columns\TextColumn;

/**
 * The low stock threshold in effect for a stock row, and whether it comes from the item or the location.
 */
class ThresholdColumn
{
    public static function make(): TextColumn
    {
        return TextColumn::make('threshold')
            ->label(__('erp.fields.low_stock_threshold'))
            ->state(fn (StockLevel $record): ?string => $record->threshold())
            ->formatStateUsing(fn (StockLevel $record): string => $record->item->unit->format($record->threshold()))
            ->description(fn (StockLevel $record): ?string => $record->min_quantity !== null ? __('erp.stock.threshold_location') : null)
            ->placeholder('—');
    }
}
