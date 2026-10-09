<?php

namespace App\Filament\Admin\Resources\InterventionCategories;

use App\Enums\InterventionCategoryKind;
use App\Enums\NavigationGroup;
use App\Filament\Admin\Resources\InterventionCategories\Pages\ManageInterventionCategories;
use App\Models\InterventionCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Intervention categories (PREPARE, CONNECT, DECONNECT, …) and subcategories with their phases
 * and tags (COLIBRI). Added, changed and removed freely; closed reports keep the old names.
 */
class InterventionCategoryResource extends Resource
{
    protected static ?string $model = InterventionCategory::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'intervention-categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Interventions;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('erp.interventions.category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.interventions.categories');
    }

    public static function form(Schema $schema): Schema
    {
        $isSubcategory = fn (Get $get): bool => $get('kind') === InterventionCategoryKind::Subcategory->value || $get('kind') === InterventionCategoryKind::Subcategory;

        return $schema
            ->columns(2)
            ->components([
                ToggleButtons::make('kind')
                    ->label(__('erp.interventions.kind'))
                    ->options(InterventionCategoryKind::class)
                    ->default(InterventionCategoryKind::Category->value)
                    ->inline()
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->label(__('erp.fields.name'))
                    ->required()
                    ->maxLength(100)
                    ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state)))
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('kind', $get('kind') instanceof InterventionCategoryKind ? $get('kind')->value : $get('kind'))),
                TextInput::make('sort')
                    ->label(__('erp.interventions.sort'))
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('frees_material')
                    ->label(__('erp.interventions.frees_material'))
                    ->helperText(__('erp.interventions.frees_material_help'))
                    ->hidden($isSubcategory)
                    ->columnSpanFull(),
                TagsInput::make('phases')
                    ->label(__('erp.interventions.phases'))
                    ->helperText(__('erp.interventions.list_help'))
                    ->placeholder('PHASE 1')
                    ->visible($isSubcategory),
                TagsInput::make('tags')
                    ->label(__('erp.interventions.tags'))
                    ->helperText(__('erp.interventions.list_help'))
                    ->placeholder('FRM220UB')
                    ->visible($isSubcategory),
                Toggle::make('is_active')
                    ->label(__('erp.fields.active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup('kind')
            ->defaultSort('sort')
            ->paginated(false)
            ->modifyQueryUsing(fn ($query) => $query->withCount('projects'))
            ->columns([
                TextColumn::make('name')->label(__('erp.fields.name'))->weight(FontWeight::Bold)->searchable(),
                TextColumn::make('details')
                    ->label(__('erp.interventions.phases_tags'))
                    ->state(fn (InterventionCategory $record): array => [...$record->phaseList(), ...$record->tagList()])
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                IconColumn::make('frees_material')->label(__('erp.interventions.frees_material'))->boolean(),
                TextColumn::make('projects_count')->label(__('erp.interventions.title'))->badge(),
                IconColumn::make('is_active')->label(__('erp.fields.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(fn (array $data): array => self::clean($data)),
                DeleteAction::make()
                    ->modalDescription(__('erp.interventions.delete_category_help')),
            ]);
    }

    /**
     * Only subcategories have phases and tags; only categories free material.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function clean(array $data): array
    {
        $kind = $data['kind'] instanceof InterventionCategoryKind ? $data['kind'] : InterventionCategoryKind::from($data['kind']);
        $list = fn (mixed $values): ?array => array_values(array_unique(array_map(fn (string $value): string => mb_strtoupper(trim($value)), array_filter((array) $values, 'filled')))) ?: null;

        return [
            ...$data,
            'frees_material' => $kind === InterventionCategoryKind::Category && ($data['frees_material'] ?? false),
            'phases' => $kind === InterventionCategoryKind::Subcategory ? $list($data['phases'] ?? []) : null,
            'tags' => $kind === InterventionCategoryKind::Subcategory ? $list($data['tags'] ?? []) : null,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInterventionCategories::route('/'),
        ];
    }
}
