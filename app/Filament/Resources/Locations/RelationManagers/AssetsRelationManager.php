<?php

namespace App\Filament\Resources\Locations\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Hidden when the assets module is off: the related policy then denies viewAny.
 */
class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.resources.asset.plural');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        // Linking a asset to a location changes the asset, so it needs that permission.
        $canAssign = fn (): bool => (bool) auth()->user()?->hasPermission('assets.update');

        return $table
            ->recordTitleAttribute('asset_tag')
            ->modifyQueryUsing(fn (Builder $query) => $query->visibleTo(auth()->user()))
            ->recordUrl(fn (Asset $record): string => AssetResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('asset_tag')
                    ->label(__('erp.resources.asset.singular'))
                    ->weight('bold'),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label(__('erp.actions.assign'))
                    ->recordSelectSearchColumns(['asset_tag', 'name'])
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
