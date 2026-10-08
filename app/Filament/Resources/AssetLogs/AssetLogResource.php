<?php

namespace App\Filament\Resources\AssetLogs;

use App\Enums\AssetLogType;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\AssetLogs\Pages\ManageAssetLogs;
use App\Filament\Resources\AssetLogs\Schemas\AssetLogForm;
use App\Filament\Resources\AssetLogs\Tables\AssetLogsTable;
use App\Models\AssetLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Damages, repairs and inspections of all assets in one list.
 */
class AssetLogResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = AssetLog::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Assets;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('erp.resources.asset_log.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.asset_log.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $open = static::getEloquentQuery()->where('type', AssetLogType::Damage)->whereNull('resolved_at')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.logs.open_damages');
    }

    public static function form(Schema $schema): Schema
    {
        return AssetLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssetLogs::route('/'),
        ];
    }
}
