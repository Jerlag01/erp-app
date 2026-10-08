<?php

namespace App\Filament\Resources\Locations\RelationManagers;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\Vehicle;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Hidden when the vehicles module is off: the related policy then denies viewAny.
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
        // Linking a vehicle to a location changes the vehicle, so it needs that permission.
        $canAssign = fn (): bool => (bool) auth()->user()?->hasPermission('vehicles.update');

        return $table
            ->recordTitleAttribute('plate_number')
            ->modifyQueryUsing(fn (Builder $query) => $query->visibleTo(auth()->user()))
            ->recordUrl(fn (Vehicle $record): string => VehicleResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('plate_number')
                    ->label(__('erp.resources.vehicle.singular'))
                    ->weight('bold'),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label(__('erp.actions.assign'))
                    ->recordSelectSearchColumns(['plate_number', 'brand', 'type'])
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->visibleTo(auth()->user()))
                    ->preloadRecordSelect()
                    ->visible($canAssign),
            ])
            ->recordActions([
                DissociateAction::make()
                    ->label(__('erp.actions.unassign'))
                    ->visible($canAssign),
            ]);
    }
}
