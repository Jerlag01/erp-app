<?php

namespace App\Filament\Resources\ItAssets;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\It\ItLogsRelationManager;
use App\Filament\Resources\ItAssets\Pages\CreateItAsset;
use App\Filament\Resources\ItAssets\Pages\EditItAsset;
use App\Filament\Resources\ItAssets\Pages\ListItAssets;
use App\Filament\Resources\ItAssets\Pages\ViewItAsset;
use App\Filament\Resources\ItAssets\RelationManagers\ComponentsRelationManager;
use App\Filament\Resources\ItAssets\RelationManagers\LicenseSeatsRelationManager;
use App\Filament\Resources\ItAssets\RelationManagers\MaintenancesRelationManager;
use App\Filament\Resources\ItAssets\Schemas\ItAssetForm;
use App\Filament\Resources\ItAssets\Schemas\ItAssetInfolist;
use App\Filament\Resources\ItAssets\Tables\ItAssetsTable;
use App\Models\ItAsset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ItAssetResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = ItAsset::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'it-assets';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::It;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'asset_tag';

    /**
     * @var list<string>
     */
    protected static array $globallySearchableAttributes = ['asset_tag', 'name', 'serial', 'hostname'];

    public static function getModelLabel(): string
    {
        return __('erp.resources.it_asset.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.it_asset.plural');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof ItAsset ? $record->label() : static::getModelLabel();
    }

    public static function getNavigationBadge(): ?string
    {
        $due = static::getEloquentQuery()->auditDue()->count();

        return $due > 0 ? (string) $due : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.it.audits_due');
    }

    public static function form(Schema $schema): Schema
    {
        return ItAssetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItAssetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItAssetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItLogsRelationManager::class,
            MaintenancesRelationManager::class,
            ComponentsRelationManager::class,
            LicenseSeatsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItAssets::route('/'),
            'create' => CreateItAsset::route('/create'),
            'view' => ViewItAsset::route('/{record}'),
            'edit' => EditItAsset::route('/{record}/edit'),
        ];
    }
}
