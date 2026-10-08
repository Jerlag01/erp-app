<?php

namespace App\Filament\Resources\Teams\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
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
        $canAssign = fn (): bool => (bool) auth()->user()?->hasPermission('assets.update');

        return $table
            ->recordTitleAttribute('asset_tag')
            ->recordUrl(fn (Asset $record): string => AssetResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('asset_tag')
                    ->label(__('erp.fields.asset_tag'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold),
                TextColumn::make('name')
                    ->label(__('erp.fields.name')),
                TextColumn::make('next_inspection_date')
                    ->label(__('erp.fields.next_inspection_date'))
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn (Asset $record): string => DueDate::color($record->next_inspection_date)),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label(__('erp.actions.assign'))
                    ->recordSelectSearchColumns(['asset_tag', 'name', 'serial_number'])
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
