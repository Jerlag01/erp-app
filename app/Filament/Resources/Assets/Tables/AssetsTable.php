<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Support\DueDate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'team', 'vehicle', 'user', 'location'])->withCount('openDamages'))
            ->defaultSort('asset_tag')
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(36)
                    ->toggleable(),
                TextColumn::make('asset_tag')
                    ->label(__('erp.fields.asset_tag'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (Asset $record): ?string => trim("{$record->brand} {$record->model}") ?: null)
                    ->searchable(['name', 'brand', 'model', 'serial_number'])
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('erp.fields.category'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('serial_number')
                    ->label(__('erp.fields.serial_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->size('xs')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('team.name')
                    ->label(__('erp.resources.team.singular'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->visible(fn (): bool => modules()->teams()),
                TextColumn::make('vehicle.plate_number')
                    ->label(__('erp.resources.vehicle.singular'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->visible(fn (): bool => modules()->fleet()),
                TextColumn::make('location.name')
                    ->label(__('erp.resources.location.singular'))
                    ->placeholder('—')
                    ->visible(fn (): bool => modules()->locations())
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label(__('erp.fields.employee'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('next_inspection_date')
                    ->label(__('erp.fields.next_inspection_date'))
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (Asset $record): string => DueDate::color($record->next_inspection_date))
                    ->description(fn (Asset $record): ?string => DueDate::description($record->next_inspection_date))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('open_damages_count')
                    ->label(__('erp.logs.open_damages'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '—')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(AssetStatus::class)
                    ->multiple(),
                SelectFilter::make('category')
                    ->label(__('erp.fields.category'))
                    ->relationship('category', 'name')
                    ->preload(),
                SelectFilter::make('team')
                    ->label(__('erp.resources.team.singular'))
                    ->relationship('team', 'name', fn (Builder $query) => $query->visibleTo(auth()->user()))
                    ->preload()
                    ->visible(fn (): bool => modules()->teams()),
                SelectFilter::make('vehicle')
                    ->label(__('erp.resources.vehicle.singular'))
                    ->relationship('vehicle', 'plate_number', fn (Builder $query) => $query->visibleTo(auth()->user()))
                    ->preload()
                    ->visible(fn (): bool => modules()->fleet()),
                Filter::make('inspection_due')
                    ->label(__('erp.due.inspections_due'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->inspectionDue()),
                Filter::make('open_damages')
                    ->label(__('erp.logs.open_damages'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->has('openDamages')),
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
