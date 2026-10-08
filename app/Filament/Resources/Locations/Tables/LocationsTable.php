<?php

namespace App\Filament\Resources\Locations\Tables;

use App\Models\Location;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(array_keys(array_filter([
                'teams' => modules()->teams(),
                'vehicles' => modules()->fleet(),
                'assets' => modules()->assets(),
            ]))))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label(__('erp.fields.code'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—'),
                TextColumn::make('city')
                    ->label(__('erp.fields.address'))
                    ->state(fn (Location $record): ?string => $record->addressLine())
                    ->url(fn (Location $record): ?string => $record->mapsUrl(), shouldOpenInNewTab: true)
                    ->placeholder('—')
                    ->searchable(['street', 'city', 'postal_code']),
                TextColumn::make('teams_count')
                    ->label(__('erp.resources.team.plural'))
                    ->badge()
                    ->color('gray')
                    ->visible(fn (): bool => modules()->teams()),
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
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
