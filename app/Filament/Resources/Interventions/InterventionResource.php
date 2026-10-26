<?php

namespace App\Filament\Resources\Interventions;

use App\Enums\InterventionReportStatus;
use App\Enums\NavigationGroup;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Interventions\Pages\ListInterventions;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\InterventionCategory;
use App\Models\Project;
use App\Support\Interventions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Interventions in their own place in the navigation: the projects in an intervention category
 * (600) the user can see. Opening one goes to the project page, where it is closed.
 */
class InterventionResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $slug = 'interventions';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Interventions;

    protected static ?int $navigationSort = 1;

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('erp.interventions.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.interventions.title');
    }

    /**
     * Everyone with project rights, and employees who lead or are planned on an intervention.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user || ! modules()->interventions()) {
            return false;
        }

        return Gate::allows('viewAny', Project::class) || static::getEloquentQuery()->exists();
    }

    public static function canCreate(): bool
    {
        return modules()->interventions() && Gate::allows('create', Project::class);
    }

    public static function canView(Model $record): bool
    {
        return Gate::allows('view', $record);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * The same interventions ProjectPolicy::view allows: all for administrators and, with
     * "projects.view", all (teams module off) or those they lead or their teams are on; plus the
     * ones the user is planned on.
     */
    public static function getEloquentQuery(): Builder
    {
        return app(Interventions::class)->visibleTo(auth()->user());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'workSite', 'interventionCategories', 'interventionSubcategory', 'interventionReport']))
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Project $record): string => ProjectResource::getUrl('view', ['record' => $record]))
            ->searchPlaceholder(__('erp.interventions.search'))
            ->columns([
                TextColumn::make('work_order_number')
                    ->label(__('erp.interventions.wo_number'))
                    ->prefix('WO ')
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('reference')
                    ->label(__('erp.projects.reference'))
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (Project $record): ?string => $record->short_description)
                    ->searchable(['reference', 'short_description', 'cow_code'])
                    ->sortable(),
                TextColumn::make('interventionCategories.name')
                    ->label(__('erp.interventions.categories'))
                    ->badge()
                    ->description(fn (Project $record): ?string => $record->interventionSubcategoryLabel())
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('interventionReport.status')
                    ->label(__('erp.interventions.review_status'))
                    ->badge()
                    ->placeholder(__('erp.interventions.not_closed')),
                TextColumn::make('interventionReport.executed_at')
                    ->label(__('erp.interventions.executed_at'))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('interventionReport.po_number')
                    ->label(__('erp.interventions.po_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('erp.fields.status'))
                    ->options(ProjectStatus::class)
                    ->multiple(),
                SelectFilter::make('category')
                    ->label(__('erp.interventions.categories'))
                    ->options(fn (): array => InterventionCategory::query()->orderBy('kind')->orderBy('sort')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $id) => $query->where(fn (Builder $query) => $query
                        ->whereHas('interventionCategories', fn (Builder $query) => $query->whereKey($id))
                        ->orWhere('intervention_subcategory_id', $id)))),
                SelectFilter::make('review')
                    ->label(__('erp.interventions.review_status'))
                    ->options(InterventionReportStatus::class)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $status) => $query->whereHas('interventionReport', fn (Builder $query) => $query->where('status', $status)))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInterventions::route('/'),
        ];
    }
}
