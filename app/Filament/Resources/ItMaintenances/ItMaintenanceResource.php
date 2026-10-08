<?php

namespace App\Filament\Resources\ItMaintenances;

use App\Enums\ItMaintenanceType;
use App\Enums\NavigationGroup;
use App\Filament\Resources\ItAssets\ItAssetResource;
use App\Filament\Resources\ItAssets\RelationManagers\MaintenancesRelationManager;
use App\Filament\Resources\ItMaintenances\Pages\ManageItMaintenances;
use App\Models\ItAsset;
use App\Models\ItMaintenance;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * All maintenance across the assets the user may see.
 */
class ItMaintenanceResource extends Resource
{
    protected static ?string $model = ItMaintenance::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'it-maintenances';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::It;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('erp.resources.it_maintenance.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.it_maintenance.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('it_asset_id', ItAsset::query()->visibleTo(auth()->user())->select('id'));
    }

    public static function form(Schema $schema): Schema
    {
        return MaintenancesRelationManager::maintenanceForm($schema, [
            Select::make('it_asset_id')
                ->label(__('erp.resources.it_asset.singular'))
                ->options(fn (): array => ItAsset::query()->visibleTo(auth()->user())->with('model.manufacturer')->orderBy('asset_tag')->get()->mapWithKeys(fn (ItAsset $asset): array => [$asset->id => $asset->label()])->all())
                ->searchable()
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset.model.manufacturer', 'supplier']))
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('start_date')
                    ->label(__('erp.fields.start_date'))
                    ->date('d/m/Y')
                    ->description(fn (ItMaintenance $record): string => $record->completion_date ? '→ '.$record->completion_date->format('d/m/Y') : __('erp.it.ongoing'))
                    ->sortable(),
                TextColumn::make('asset.asset_tag')
                    ->label(__('erp.resources.it_asset.singular'))
                    ->description(fn (ItMaintenance $record): ?string => $record->asset?->model?->fullName())
                    ->url(fn (ItMaintenance $record): string => ItAssetResource::getUrl('view', ['record' => $record->it_asset_id]))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('erp.fields.type'))
                    ->badge(),
                TextColumn::make('title')
                    ->label(__('erp.fields.title'))
                    ->description(fn (ItMaintenance $record): ?string => $record->supplier?->name)
                    ->searchable()
                    ->wrap(),
                IconColumn::make('is_warranty')
                    ->label(__('erp.fields.is_warranty'))
                    ->boolean(),
                TextColumn::make('cost')
                    ->label(__('erp.fields.cost'))
                    ->money('EUR', locale: 'nl_BE')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('erp.fields.type'))
                    ->options(ItMaintenanceType::class),
                TernaryFilter::make('ongoing')
                    ->label(__('erp.it.ongoing'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('completion_date'),
                        false: fn (Builder $query) => $query->whereNotNull('completion_date'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageItMaintenances::route('/'),
        ];
    }
}
