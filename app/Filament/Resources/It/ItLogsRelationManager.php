<?php

namespace App\Filament\Resources\It;

use App\Models\ItLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * History (checkouts, checkins, audits…) of an IT asset, license or item.
 */
class ItLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedClock;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.stock.history');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['target', 'user']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('erp.fields.date'))
                    ->dateTime('d/m/Y H:i')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('action')
                    ->label(__('erp.it.action'))
                    ->badge(),
                TextColumn::make('target')
                    ->label(__('erp.it.target'))
                    ->state(fn (ItLog $record): ?string => $record->targetName())
                    ->description(fn (ItLog $record): ?string => $record->quantity ? '× '.$record->quantity : null)
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label(__('erp.it.by'))
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label(__('erp.fields.note'))
                    ->wrap()
                    ->placeholder('—'),
            ]);
    }
}
