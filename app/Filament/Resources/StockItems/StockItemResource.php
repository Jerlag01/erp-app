<?php

namespace App\Filament\Resources\StockItems;

use App\Enums\NavigationGroup;
use App\Filament\Resources\StockItems\Pages\CreateStockItem;
use App\Filament\Resources\StockItems\Pages\EditStockItem;
use App\Filament\Resources\StockItems\Pages\ListStockItems;
use App\Filament\Resources\StockItems\Pages\ViewStockItem;
use App\Filament\Resources\StockItems\RelationManagers\LevelsRelationManager;
use App\Filament\Resources\StockItems\RelationManagers\MovementsRelationManager;
use App\Filament\Resources\StockItems\Schemas\StockItemForm;
use App\Filament\Resources\StockItems\Schemas\StockItemInfolist;
use App\Filament\Resources\StockItems\Tables\StockItemsTable;
use App\Models\StockItem;
use App\Models\StockLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StockItemResource extends Resource
{
    protected static ?string $model = StockItem::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'stock-items';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Stock;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @var list<string>
     */
    protected static array $globallySearchableAttributes = ['name', 'manufacturer_reference', 'distributor_reference', 'ean'];

    public static function getModelLabel(): string
    {
        return __('erp.resources.stock_item.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.stock_item.plural');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            __('erp.fields.manufacturer_reference') => $record->manufacturer_reference,
            __('erp.fields.distributor_reference') => $record->distributor_reference,
        ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $low = $user ? StockLevel::query()->visibleTo($user)->low()->count() : 0;

        return $low > 0 ? (string) $low : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.stock.low');
    }

    public static function form(Schema $schema): Schema
    {
        return StockItemForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockItemInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LevelsRelationManager::class,
            MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockItems::route('/'),
            'create' => CreateStockItem::route('/create'),
            'view' => ViewStockItem::route('/{record}'),
            'edit' => EditStockItem::route('/{record}/edit'),
        ];
    }
}
