<?php

namespace App\Filament\Resources\Vehicles;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Resources\Vehicles\Pages\ViewVehicle;
use App\Filament\Resources\Vehicles\RelationManagers\AssetsRelationManager;
use App\Filament\Resources\Vehicles\Schemas\VehicleForm;
use App\Filament\Resources\Vehicles\Tables\VehiclesTable;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VehicleResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = Vehicle::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Fleet;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'plate_number';

    /**
     * @var list<string>
     */
    protected static array $globallySearchableAttributes = ['plate_number', 'vin', 'brand', 'type'];

    public static function getModelLabel(): string
    {
        return __('erp.resources.vehicle.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.vehicle.plural');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Vehicle ? $record->label() : static::getModelLabel();
    }

    public static function getNavigationBadge(): ?string
    {
        $due = static::getEloquentQuery()->controlDue()->count();

        return $due > 0 ? (string) $due : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.due.controls_due');
    }

    public static function form(Schema $schema): Schema
    {
        return VehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AssetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'view' => ViewVehicle::route('/{record}'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
