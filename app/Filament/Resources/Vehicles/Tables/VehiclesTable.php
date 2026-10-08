<?php

namespace App\Filament\Resources\Vehicles\Tables;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Models\Vehicle;
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

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['team', 'driver', 'location']))
            ->defaultSort('next_control_date')
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(36)
                    ->toggleable(),
                TextColumn::make('plate_number')
                    ->label(__('erp.fields.plate_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('brand')
                    ->label(__('erp.fields.vehicle'))
                    ->formatStateUsing(fn (Vehicle $record): string => "{$record->brand} {$record->type}")
                    ->description(fn (Vehicle $record): ?string => $record->year ? (string) $record->year : null)
                    ->searchable(['brand', 'type'])
                    ->sortable(),
                TextColumn::make('vin')
                    ->label(__('erp.fields.vin'))
                    ->fontFamily(FontFamily::Mono)
                    ->size('xs')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('team.name')
                    ->label(__('erp.resources.team.singular'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->visible(fn (): bool => modules()->teams()),
                TextColumn::make('location.name')
                    ->label(__('erp.resources.location.singular'))
                    ->placeholder('—')
                    ->visible(fn (): bool => modules()->locations())
                    ->toggleable(),
                TextColumn::make('driver.name')
                    ->label(__('erp.fields.driver'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('control_date')
                    ->label(__('erp.fields.control_date'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('next_control_date')
                    ->label(__('erp.fields.next_control_date'))
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (Vehicle $record): string => DueDate::color($record->next_control_date))
                    ->description(fn (Vehicle $record): ?string => DueDate::description($record->next_control_date))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('mileage')
                    ->label(__('erp.fields.mileage'))
                    ->numeric(thousandsSeparator: '.')
                    ->suffix(' km')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(VehicleStatus::class)
                    ->multiple(),
                SelectFilter::make('team')
                    ->label(__('erp.resources.team.singular'))
                    ->relationship('team', 'name', fn (Builder $query) => $query->visibleTo(auth()->user()))
                    ->preload()
                    ->visible(fn (): bool => modules()->teams()),
                SelectFilter::make('fuel')
                    ->label(__('erp.fields.fuel'))
                    ->options(FuelType::class),
                Filter::make('control_due')
                    ->label(__('erp.due.controls_due'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->controlDue()),
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
