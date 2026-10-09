<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\InterventionCategoryKind;
use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\ProjectCategories\ProjectCategoryResource;
use App\Models\Country;
use App\Models\InterventionCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPoNumber;
use App\Models\WorkSite;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        $category = fn (Get $get): ?ProjectCategory => self::category($get('project_category_id'));
        $usesWorkSite = fn (Get $get): bool => (bool) $category($get)?->numbering->usesWorkSite();
        $isIntervention = fn (Get $get): bool => (bool) $category($get)?->numbering->isIntervention();
        $interventionFields = fn (Get $get): bool => $isIntervention($get) && modules()->interventions();
        $subcategory = fn (Get $get): ?InterventionCategory => filled($get('intervention_subcategory_id')) ? InterventionCategory::find($get('intervention_subcategory_id')) : null;

        return $schema
            ->components([
                Grid::make(['lg' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Section::make(__('erp.sections.project'))
                            ->columnSpan(['lg' => 2])
                            ->columns(2)
                            ->schema([
                                // The category decides the reference, so it can't change once the project exists.
                                Select::make('project_category_id')
                                    ->label(__('erp.resources.project_category.singular'))
                                    ->relationship('category', 'code', fn (Builder $query, string $operation) => $query
                                        ->when($operation === 'create', fn (Builder $query) => $query->where('is_active', true))
                                        ->orderBy('code'))
                                    ->getOptionLabelFromRecordUsing(fn (ProjectCategory $record): string => $record->label())
                                    ->helperText(fn (Get $get): ?string => ($selected = $category($get))
                                        ? trim(($selected->description ? $selected->description.' · ' : '').__('erp.projects.example', ['reference' => ProjectCategoryResource::exampleReference($selected)]))
                                        : null)
                                    ->required()
                                    ->live()
                                    // Interventions start as "to be executed", so they can be planned straight away.
                                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                                        if (self::category($state)?->numbering->isIntervention()) {
                                            $set('status', ProjectStatus::ToBeExecuted->value);
                                        }
                                    })
                                    ->disabledOn('edit')
                                    ->columnSpanFull(),
                                TextInput::make('work_order_number')
                                    ->label(__('erp.interventions.wo_number'))
                                    ->helperText(__('erp.interventions.wo_number_help'))
                                    ->prefix('WO')
                                    ->required($isIntervention)
                                    ->maxLength(30)
                                    ->regex('/^(WO\s*)?[A-Za-z0-9\/._-]+$/i')
                                    // Unique as stored: "WO 123456" and "123456" are the same work order.
                                    ->rule(fn (?Project $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                        if (Project::query()->where('work_order_number', self::workOrderNumber($value))->when($record, fn (Builder $query) => $query->whereKeyNot($record->id))->exists()) {
                                            $fail(__('validation.unique', ['attribute' => __('erp.interventions.wo_number')]));
                                        }
                                    })
                                    ->dehydrateStateUsing(fn (?string $state): ?string => self::workOrderNumber($state))
                                    ->visible($isIntervention)
                                    ->extraInputAttributes(['style' => 'font-family: var(--erp-mono);']),
                                TextInput::make('number')
                                    ->label(__('erp.projects.number'))
                                    ->helperText(__('erp.projects.number_help'))
                                    ->placeholder(fn (Get $get): ?string => ($selected = $category($get)) ? $selected->numbering->formatNumber($selected->nextNumber()) : null)
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(999999)
                                    ->rule(fn (Get $get) => Rule::unique('projects', 'number')->where('project_category_id', $get('project_category_id')))
                                    ->visibleOn('create'),
                                Select::make('work_site_id')
                                    ->label(__('erp.resources.work_site.singular'))
                                    ->helperText(__('erp.projects.work_site_help'))
                                    ->relationship('workSite', 'cow_code', fn (Builder $query) => $query->orderBy('cow_code'))
                                    ->getOptionLabelFromRecordUsing(fn (WorkSite $record): string => trim($record->cow_code.' · '.$record->city, ' ·'))
                                    ->searchable(['cow_code', 'city', 'street'])
                                    ->preload()
                                    ->required($usesWorkSite)
                                    ->visible(fn (Get $get): bool => $usesWorkSite($get) && modules()->workSites()),
                                TextInput::make('short_description')
                                    ->label(__('erp.projects.short_description'))
                                    ->placeholder(__('erp.projects.short_description_placeholder'))
                                    ->maxLength(255)
                                    ->visible(fn (Get $get): bool => (bool) $category($get)?->has_short_description)
                                    ->columnSpanFull(),
                                Select::make('status')
                                    ->label(__('erp.fields.status'))
                                    ->options(ProjectStatus::class)
                                    ->default(ProjectStatus::Offer)
                                    ->required(),
                                Textarea::make('notes')
                                    ->label(__('erp.fields.notes'))
                                    ->rows(3)
                                    ->maxLength(5000)
                                    ->columnSpanFull(),
                            ]),
                        Section::make(__('erp.projects.people'))
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                Select::make('leaders')
                                    ->label(__('erp.projects.leaders'))
                                    ->helperText(__('erp.projects.leaders_help'))
                                    ->relationship('leaders', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->default(fn (): array => array_filter([auth()->id()])),
                                Select::make('teams')
                                    ->label(__('erp.resources.team.plural'))
                                    ->helperText(__('erp.projects.teams_help'))
                                    ->relationship('teams', 'name', fn (Builder $query) => $query->orderBy('name'))
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (): bool => modules()->teams()),
                            ]),
                    ]),
                Section::make(__('erp.interventions.title'))
                    ->description(__('erp.interventions.form_help'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->visible($interventionFields)
                    ->schema([
                        Select::make('interventionCategories')
                            ->label(__('erp.interventions.categories'))
                            ->relationship('interventionCategories', 'name', fn (Builder $query, ?Project $record) => $query
                                ->where('kind', InterventionCategoryKind::Category->value)
                                ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('intervention_categories.id', $record?->interventionCategories()->pluck('intervention_categories.id')->all() ?: [0])))
                            ->multiple()
                            ->preload()
                            ->columnSpanFull(),
                        Select::make('intervention_subcategory_id')
                            ->label(__('erp.interventions.subcategory'))
                            ->options(fn (?Project $record): array => InterventionCategory::options(InterventionCategoryKind::Subcategory, array_filter([$record?->intervention_subcategory_id])))
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('intervention_phase', null);
                                $set('intervention_tags', []);
                            }),
                        Select::make('intervention_phase')
                            ->label(__('erp.interventions.phase'))
                            ->options(fn (Get $get): array => collect($subcategory($get)?->phaseList() ?? [])->mapWithKeys(fn (string $phase): array => [$phase => $phase])->all())
                            ->visible(fn (Get $get): bool => (bool) $subcategory($get)?->phaseList()),
                        Select::make('intervention_tags')
                            ->label(__('erp.interventions.tags'))
                            ->multiple()
                            ->options(fn (Get $get): array => collect($subcategory($get)?->tagList() ?? [])->mapWithKeys(fn (string $tag): array => [$tag => $tag])->all())
                            ->visible(fn (Get $get): bool => (bool) $subcategory($get)?->tagList()),
                    ]),
                // Interventions get their PO number when an administrator approves them.
                Section::make(__('erp.projects.po_numbers'))
                    ->description(__('erp.projects.po_numbers_help'))
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => (bool) $category($get)?->numbering->hasPoNumbers() && ! $interventionFields($get))
                    ->schema([
                        Repeater::make('poNumbers')
                            ->hiddenLabel()
                            ->relationship()
                            ->simple(
                                TextInput::make('number')
                                    ->placeholder('4500123456')
                                    ->required()
                                    ->length(10)
                                    ->regex(ProjectPoNumber::PATTERN)
                                    ->validationMessages(['regex' => __('erp.projects.po_number_invalid')])
                                    ->distinct()
                                    ->extraInputAttributes(['inputmode' => 'numeric', 'style' => 'font-family: var(--erp-mono);']),
                            )
                            ->defaultItems(0)
                            ->addActionLabel(__('erp.projects.add_po_number'))
                            ->grid(['md' => 2, 'xl' => 3]),
                    ]),
                Section::make(__('erp.projects.client'))
                    ->description(__('erp.projects.client_help'))
                    ->columnSpanFull()
                    ->columns(6)
                    ->visible(fn (Get $get): bool => (bool) $category($get)?->numbering->hasClient())
                    ->schema([
                        TextInput::make('client_name')
                            ->label(__('erp.projects.client_name'))
                            ->required(fn (Get $get): bool => (bool) $category($get)?->numbering->hasClient())
                            ->maxLength(255)
                            ->columnSpan(3),
                        TextInput::make('client_company')
                            ->label(__('erp.fields.company'))
                            ->maxLength(255)
                            ->columnSpan(3),
                        TextInput::make('client_phone')
                            ->label(__('erp.fields.phone'))
                            ->tel()
                            ->maxLength(50)
                            ->columnSpan(3),
                        TextInput::make('client_email')
                            ->label(__('erp.fields.email'))
                            ->email()
                            ->maxLength(255)
                            ->columnSpan(3),
                        TextInput::make('street')
                            ->label(__('erp.fields.street_only'))
                            ->maxLength(150)
                            ->columnSpan(4),
                        TextInput::make('house_number')
                            ->label(__('erp.fields.house_number'))
                            ->maxLength(20)
                            ->columnSpan(1),
                        TextInput::make('addition')
                            ->label(__('erp.fields.addition'))
                            ->maxLength(20)
                            ->columnSpan(1),
                        TextInput::make('postal_code')
                            ->label(__('erp.fields.postal_code'))
                            ->maxLength(20)
                            ->columnSpan(2),
                        TextInput::make('city')
                            ->label(__('erp.fields.city'))
                            ->maxLength(100)
                            ->columnSpan(2),
                        Select::make('country_id')
                            ->label(__('erp.fields.country'))
                            ->options(fn (): array => Country::options())
                            ->default(fn (): ?int => Country::belgiumId())
                            ->searchable()
                            ->columnSpan(2),
                    ]),
            ]);
    }

    /**
     * "wo 123456" → "123456".
     */
    public static function workOrderNumber(mixed $value): ?string
    {
        return filled($value) ? strtoupper(trim((string) preg_replace('/^WO\s*/i', '', trim((string) $value)))) : null;
    }

    /**
     * Categories are looked up once per request, as many fields depend on the selected one.
     */
    public static function category(mixed $id): ?ProjectCategory
    {
        static $categories = [];

        if (blank($id)) {
            return null;
        }

        return $categories[(int) $id] ??= ProjectCategory::find($id);
    }

    /**
     * Keeps the client fields of private projects only.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function clean(array $data, ?Project $project = null): array
    {
        $category = self::category($data['project_category_id'] ?? $project?->project_category_id);

        if ($category && ! $category->numbering->isIntervention()) {
            $data['work_order_number'] = null;
            $data['intervention_subcategory_id'] = null;
            $data['intervention_phase'] = null;
            $data['intervention_tags'] = null;
        }

        if (filled($data['intervention_phase'] ?? null) || filled($data['intervention_tags'] ?? null)) {
            $subcategory = InterventionCategory::query()->find($data['intervention_subcategory_id'] ?? null);
            $data['intervention_phase'] = in_array($data['intervention_phase'] ?? null, $subcategory?->phaseList() ?? [], true) ? $data['intervention_phase'] : null;
            $data['intervention_tags'] = array_values(array_intersect((array) ($data['intervention_tags'] ?? []), $subcategory?->tagList() ?? [])) ?: null;
        }

        if ($category && ! $category->numbering->hasClient()) {
            foreach (['client_name', 'client_company', 'client_email', 'client_phone', 'street', 'house_number', 'addition', 'postal_code', 'city', 'country_id'] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
