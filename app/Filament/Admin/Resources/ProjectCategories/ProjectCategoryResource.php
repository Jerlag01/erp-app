<?php

namespace App\Filament\Admin\Resources\ProjectCategories;

use App\Enums\NavigationGroup;
use App\Enums\ProjectNumbering;
use App\Filament\Admin\Resources\ProjectCategories\Pages\ManageProjectCategories;
use App\Models\ProjectCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The main project categories (604, 605, …). The code and the numbering are locked once a
 * category holds projects, so existing references never change; such a category cannot be deleted.
 */
class ProjectCategoryResource extends Resource
{
    protected static ?string $model = ProjectCategory::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'project-categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Projects;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getModelLabel(): string
    {
        return __('erp.resources.project_category.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.resources.project_category.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $locked = fn (?ProjectCategory $record): bool => (bool) $record?->hasProjects();

        return $schema
            ->components([
                TextInput::make('code')
                    ->label(__('erp.projects.category_code'))
                    ->helperText(fn (?ProjectCategory $record): ?string => $locked($record) ? __('erp.projects.category_locked') : null)
                    ->placeholder('609')
                    ->required()
                    ->regex('/^\d{3,6}$/')
                    ->maxLength(6)
                    ->unique(ignoreRecord: true)
                    ->disabled($locked)
                    ->extraInputAttributes(['style' => 'font-family: var(--erp-mono); font-weight: 700;']),
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->required()
                    ->maxLength(150),
                Textarea::make('description')
                    ->label(__('erp.fields.description'))
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Radio::make('numbering')
                    ->label(__('erp.projects.numbering'))
                    ->options(ProjectNumbering::class)
                    ->default(ProjectNumbering::Sequence)
                    ->required()
                    ->disabled($locked)
                    ->columnSpanFull(),
                Toggle::make('has_short_description')
                    ->label(__('erp.projects.has_short_description'))
                    ->helperText(__('erp.projects.has_short_description_help')),
                Toggle::make('is_active')
                    ->label(__('erp.fields.active'))
                    ->helperText(__('erp.projects.category_active_help'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->paginated(false)
            ->modifyQueryUsing(fn ($query) => $query->withCount('projects'))
            ->columns([
                TextColumn::make('code')
                    ->label(__('erp.projects.category_code'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->size('lg')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('erp.fields.name'))
                    ->description(fn (ProjectCategory $record): ?string => $record->description)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('numbering')
                    ->label(__('erp.projects.numbering'))
                    ->badge()
                    ->color('gray')
                    ->description(fn (ProjectCategory $record): string => __('erp.projects.example', ['reference' => self::exampleReference($record)])),
                IconColumn::make('has_short_description')
                    ->label(__('erp.projects.short_description'))
                    ->boolean(),
                TextColumn::make('projects_count')
                    ->label(__('erp.resources.project.plural'))
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('erp.fields.active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (ProjectCategory $record): bool => ! $record->hasProjects()),
            ]);
    }

    public static function exampleReference(ProjectCategory $category): string
    {
        return match ($category->numbering) {
            ProjectNumbering::Yearly => "{$category->code}-".now()->format('y').'001-02GAM',
            ProjectNumbering::Private => "{$category->code}-001",
            default => "{$category->code}-001-02GAM",
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProjectCategories::route('/'),
        ];
    }
}
