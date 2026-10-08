<?php

namespace App\Filament\Resources\Teams\RelationManagers;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\Vehicle;
use App\Support\DueDate;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Only shown when both modules are on: the related policy denies viewAny when its module is off.
 */
class VehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicles';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.resources.vehicle.plural');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $canAssign = fn (): bool => (bool) auth()->user()?->hasPermission('vehicles.update');

        return $table
            ->recordTitleAttribute('plate_number')
            ->recordUrl(fn (Vehicle $record): string => VehicleResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('plate_number')
                    ->label(__('erp.fields.plate_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold),
                TextColumn::make('brand')
                    ->label(__('erp.fields.vehicle'))
                    ->formatStateUsing(fn (Vehicle $record): string => "{$record->brand} {$record->type}"),
                TextColumn::make('next_control_date')
                    ->label(__('erp.fields.next_control_date'))
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (Vehicle $record): string => DueDate::color($record->next_control_date)),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label(__('erp.actions.assign'))
                    ->recordSelectSearchColumns(['plate_number', 'brand', 'type'])
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->visibleTo(auth()->user()))
                    ->visible($canAssign),
            ])
            ->recordActions([
                DissociateAction::make()
                    ->label(__('erp.actions.unassign'))
                    ->visible($canAssign),
            ])
            ->toolbarActions([
                DissociateBulkAction::make()
                    ->label(__('erp.actions.unassign'))
                    ->visible($canAssign),
            ]);
    }
}
