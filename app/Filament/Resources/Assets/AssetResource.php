<?php

namespace App\Filament\Resources\Assets;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Assets\RelationManagers\LogsRelationManager;
use App\Filament\Resources\Assets\Schemas\AssetForm;
use App\Filament\Resources\Assets\Tables\AssetsTable;
use App\Models\Asset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AssetResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = Asset::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Assets;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @var list<string>
     */
    protected static array $globallySearchableAttributes = ['asset_tag', 'name', 'serial_number', 'brand', 'model'];

    public static function getModelLabel(): string
    {
        return __('erp.resources.asset.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.asset.plural');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Asset ? "{$record->asset_tag} · {$record->name}" : static::getModelLabel();
    }

    public static function getNavigationBadge(): ?string
    {
        $due = static::getEloquentQuery()->inspectionDue()->count();

        return $due > 0 ? (string) $due : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.due.inspections_due');
    }

    public static function form(Schema $schema): Schema
    {
        return AssetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssets::route('/'),
            'create' => CreateAsset::route('/create'),
            'view' => ViewAsset::route('/{record}'),
            'edit' => EditAsset::route('/{record}/edit'),
        ];
    }
}
