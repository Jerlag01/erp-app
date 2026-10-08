<?php

namespace App\Filament\Resources\WorkSites\RelationManagers;

use App\Models\WorkSiteTypeChange;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The log of building type changes; read-only.
 */
class TypeChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'typeChanges';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedClock;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.work_sites.type_log');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('changed_on')
                    ->label(__('erp.work_sites.changed_on'))
                    ->date('d/m/Y')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('from_type')
                    ->label(__('erp.work_sites.from_type'))
                    ->badge()
                    ->placeholder(__('erp.work_sites.created')),
                TextColumn::make('to_type')
                    ->label(__('erp.work_sites.to_type'))
                    ->badge(),
                TextColumn::make('reason')
                    ->label(__('erp.work_sites.reason'))
                    ->description(fn (WorkSiteTypeChange $record): ?string => $record->remarks)
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label(__('erp.it.by'))
                    ->placeholder('—'),
            ]);
    }
}
