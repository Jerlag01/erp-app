<?php

namespace App\Filament\Resources\Projects;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\ScopesToVisibleRecords;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ExtraFilesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\OffersRelationManager;
use App\Filament\Resources\Projects\RelationManagers\PartsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\PlansRelationManager;
use App\Filament\Resources\Projects\RelationManagers\SchematicsRelationManager;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Schemas\ProjectInfolist;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Projects, scoped to the ones the user leads or one of their teams is assigned to.
 */
class ProjectResource extends Resource
{
    use ScopesToVisibleRecords;

    protected static ?string $model = Project::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'projects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Projects;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    /**
     * @var list<string>
     */
    protected static array $globallySearchableAttributes = ['reference', 'short_description', 'client_name', 'poNumbers.number'];

    public static function getModelLabel(): string
    {
        return __('erp.resources.project.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.project.plural');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            __('erp.fields.status') => $record->status?->getLabel(),
            __('erp.fields.description') => $record->subtitle(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PartsRelationManager::class,
            DocumentsRelationManager::class,
            ImagesRelationManager::class,
            PlansRelationManager::class,
            SchematicsRelationManager::class,
            ExtraFilesRelationManager::class,
            OffersRelationManager::class,
            InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
