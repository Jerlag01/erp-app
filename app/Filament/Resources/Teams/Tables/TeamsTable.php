<?php

namespace App\Filament\Resources\Teams\Tables;

use App\Models\Team;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['leader', 'location'])->withCount(array_keys(array_filter([
                'members' => true,
                'vehicles' => modules()->fleet(),
                'assets' => modules()->assets(),
            ]))))
            ->defaultSort('name')
            ->columns([
                ColorColumn::make('color')
                    ->label('')
                    ->width('1%'),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (Team $record): ?string => str($record->description)->limit(60)->toString() ?: null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.name')
                    ->label(__('erp.resources.location.singular'))
                    ->placeholder('—')
                    ->visible(fn (): bool => modules()->locations())
                    ->toggleable(),
                TextColumn::make('leader.name')
                    ->label(__('erp.fields.leader'))
                    ->placeholder('—'),
                TextColumn::make('members_count')
                    ->label(__('erp.sections.members'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('vehicles_count')
                    ->label(__('erp.resources.vehicle.plural'))
                    ->badge()
                    ->color('gray')
                    ->visible(fn (): bool => modules()->fleet()),
                TextColumn::make('assets_count')
                    ->label(__('erp.resources.asset.plural'))
                    ->badge()
                    ->color('gray')
                    ->visible(fn (): bool => modules()->assets()),
                IconColumn::make('is_active')
                    ->label(__('erp.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('erp.fields.is_active')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
