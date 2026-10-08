<?php

namespace App\Filament\Resources\WorkSites;

use App\Enums\NavigationGroup;
use App\Filament\Resources\WorkSites\Pages\CreateWorkSite;
use App\Filament\Resources\WorkSites\Pages\EditWorkSite;
use App\Filament\Resources\WorkSites\Pages\ListWorkSites;
use App\Filament\Resources\WorkSites\Pages\ViewWorkSite;
use App\Filament\Resources\WorkSites\RelationManagers\RemarksRelationManager;
use App\Filament\Resources\WorkSites\RelationManagers\TypeChangesRelationManager;
use App\Filament\Resources\WorkSites\Schemas\WorkSiteForm;
use App\Filament\Resources\WorkSites\Schemas\WorkSiteInfolist;
use App\Filament\Resources\WorkSites\Tables\WorkSitesTable;
use App\Models\WorkSite;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WorkSiteResource extends Resource
{
    protected static ?string $model = WorkSite::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'work-sites';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::WorkSites;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'cow_code';

    /**
     * @var list<string>
     */
    protected static array $globallySearchableAttributes = ['cow_code', 'city', 'street'];

    public static function getModelLabel(): string
    {
        return __('erp.resources.work_site.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.work_site.plural');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            __('erp.fields.building_type') => $record->building_type?->getLabel(),
            __('erp.fields.address') => $record->addressLine(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return WorkSiteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkSiteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkSitesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RemarksRelationManager::class,
            TypeChangesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkSites::route('/'),
            'create' => CreateWorkSite::route('/create'),
            'view' => ViewWorkSite::route('/{record}'),
            'edit' => EditWorkSite::route('/{record}/edit'),
        ];
    }
}
