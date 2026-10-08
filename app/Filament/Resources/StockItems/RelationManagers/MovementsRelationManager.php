<?php

namespace App\Filament\Resources\StockItems\RelationManagers;

use App\Models\StockItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who took or added how much, where and when.
 */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedArrowsRightLeft;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.stock.history');
    }

    public function table(Table $table): Table
    {
        /** @var StockItem $item */
        $item = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereIn('stock_movements.stock_level_id', StockLevel::query()->visibleTo(auth()->user())->select('id'))
                ->with(['level.location', 'user']))
            ->defaultSort('stock_movements.created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('erp.fields.date'))
                    ->dateTime('d/m/Y H:i')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('level.location.name')
                    ->label(__('erp.resources.location.singular')),
                TextColumn::make('change')
                    ->label(__('erp.stock.change'))
                    ->formatStateUsing(fn (StockMovement $record): string => ((float) $record->change > 0 ? '+' : '−').$item->unit->format(abs((float) $record->change)))
                    ->color(fn (StockMovement $record): string => (float) $record->change > 0 ? 'success' : 'danger')
                    ->weight('bold'),
                TextColumn::make('quantity_after')
                    ->label(__('erp.stock.after'))
                    ->formatStateUsing(fn (StockMovement $record): string => $item->unit->format($record->quantity_after)),
                TextColumn::make('user.name')
                    ->label(__('erp.fields.user'))
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label(__('erp.fields.note'))
                    ->placeholder('—')
                    ->wrap(),
            ]);
    }
}
