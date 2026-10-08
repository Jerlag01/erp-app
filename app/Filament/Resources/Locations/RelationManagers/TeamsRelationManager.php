<?php

namespace App\Filament\Resources\Locations\RelationManagers;

use App\Filament\Resources\Teams\TeamResource;
use App\Models\Team;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Hidden when the teams module is off: the related policy then denies viewAny.
 */
class TeamsRelationManager extends RelationManager
{
    protected static string $relationship = 'teams';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.resources.team.plural');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        // Linking a team to a location changes the team, so it needs that permission.
        $canAssign = fn (): bool => (bool) auth()->user()?->hasPermission('teams.update');

        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->visibleTo(auth()->user()))
            ->recordUrl(fn (Team $record): string => TeamResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('erp.resources.team.singular'))
                    ->weight('bold'),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label(__('erp.actions.assign'))
                    ->recordSelectSearchColumns(['name'])
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
