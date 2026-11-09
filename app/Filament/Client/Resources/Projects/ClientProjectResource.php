<?php

namespace App\Filament\Client\Resources\Projects;

use App\Enums\GpwStatus;
use App\Enums\ProjectStatus;
use App\Filament\Client\ClientResource;
use App\Models\BatteryDelivery;
use App\Models\GpwRequest;
use App\Models\Project;
use App\Models\User;
use App\Support\BatteryReports;
use App\Support\ClientPortal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * All projects of the categories the client user may see (600, 604, 605, 608, 610–650): number,
 * COW code, status, PO numbers, for a 604 whether its batteries still have to be delivered (with the
 * expected date), and when it is planned to be executed (first and last day in the planning). No amounts.
 * Opening a project shows its overview: COW code, address, status, GPW requests and the project leaders
 * with their e-mail address, never files. A closed battery room (609) shows its files with the "Files"
 * button, except the invoices, electrical schematics and plans (ClientPortal::sharedFiles()).
 */
class ClientProjectResource extends ClientResource
{
    protected static ?string $model = Project::class;

    protected static ?string $slug = 'projects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 1;

    public static function permission(): string
    {
        return ClientPortal::PROJECTS;
    }

    public static function moduleEnabled(): bool
    {
        return modules()->projects();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.client_portal.projects');
    }

    public static function getModelLabel(): string
    {
        return __('erp.client_portal.project');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.client_portal.projects');
    }

    public static function getEloquentQuery(): Builder
    {
        return static::visibleProjects()
            ->with(['category', 'workSite', 'poNumbers', 'batteryDeliveries', 'gpwRequests' => fn ($query) => $query->where('status', '!=', GpwStatus::Cancelled)])
            ->withMin('planningItems', 'date')
            ->withMax('planningItems', 'date');
    }

    /**
     * The projects the client user sees, without extra columns (for counts per status or area).
     *
     * @return Builder<Project>
     */
    public static function visibleProjects(): Builder
    {
        $query = Project::query();

        return ClientPortal::user()?->restrictProjects($query) ?? $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        $categories = ClientPortal::user()?->projectCategories() ?? [];

        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading(__('erp.client_portal.no_projects'))
            ->columns([
                TextColumn::make('reference')
                    ->label(__('erp.client_portal.project_number'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                // The number with the category's name ("604 · Batterijvervanging") and the project's description
                // under it, so the client sees what it belongs to.
                TextColumn::make('category.code')
                    ->label(__('erp.client_portal.category'))
                    ->formatStateUsing(fn (Project $record): string => $record->category?->label() ?? '')
                    ->badge()
                    ->color('gray')
                    ->description(fn (Project $record): ?string => $record->short_description ?: null),
                TextColumn::make('cow_code')
                    ->label(__('erp.client_portal.cow_code'))
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (Project $record): ?string => $record->workSite?->city)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('short_description')
                    ->label(__('erp.client_portal.description'))
                    ->state(fn (Project $record): ?string => $record->subtitle())
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('erp.client_portal.status'))
                    ->badge()
                    ->sortable(),
                // 604: "To be delivered · 12/11/2026" while batteries are not delivered yet, then "Delivered".
                TextColumn::make('battery_delivery')
                    ->label(__('erp.client_portal.battery_delivery'))
                    ->state(fn (Project $record): ?string => self::deliveryState($record))
                    ->badge()
                    ->color(fn (Project $record): string => self::openDeliveries($record)->isNotEmpty() ? 'warning' : 'success')
                    ->placeholder('—')
                    ->visible(in_array(BatteryReports::CATEGORY, $categories, true)),
                // When it is in our planning: the first and the last day.
                TextColumn::make('planned_execution')
                    ->label(__('erp.client_portal.planned_execution'))
                    ->state(fn (Project $record): ?string => self::plannedExecution($record))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('planning_items_min_date', $direction))
                    ->placeholder('—'),
                // The latest GPW request: its period, and whether it is approved or planned.
                TextColumn::make('gpw')
                    ->label(__('erp.gpw.short'))
                    ->state(fn (Project $record): ?string => ($gpw = $record->gpwRequests->first()) ? $gpw->status->getLabel().' · '.$gpw->period() : null)
                    ->badge()
                    ->color(fn (Project $record): string => $record->gpwRequests->first()?->status->getColor() ?? 'gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('po_numbers')
                    ->label(__('erp.client_portal.po_numbers'))
                    ->state(fn (Project $record): array => $record->poNumbers->pluck('number')->all())
                    ->fontFamily(FontFamily::Mono)
                    ->listWithLineBreaks()
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('poNumbers', fn (Builder $query) => $query->where('number', 'like', '%'.addcslashes($search, '%_\\').'%')))
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('erp.client_portal.created'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('files')
                    ->label(__('erp.client_portal.files'))
                    ->icon(Heroicon::OutlinedFolderOpen)
                    ->visible(fn (Project $record): bool => ClientPortal::sharesFiles($record))
                    ->modalHeading(fn (Project $record): string => __('erp.client_portal.files_of', ['reference' => $record->reference]))
                    ->modalContent(fn (Project $record) => view('filament.client.project-files', ['project' => $record, 'files' => ClientPortal::sharedFiles($record)]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('erp.actions.close')),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('erp.client_portal.category'))
                    ->options(array_intersect_key(ClientPortal::categoryOptions(), array_flip($categories)))
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, string $code) => $query->whereHas('category', fn (Builder $query) => $query->where('code', $code)))),
                SelectFilter::make('status')
                    ->label(__('erp.client_portal.status'))
                    ->options(ProjectStatus::class)
                    ->multiple(),
                TernaryFilter::make('has_po')
                    ->label(__('erp.client_portal.has_po'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('poNumbers'),
                        false: fn (Builder $query) => $query->whereDoesntHave('poNumbers'),
                    ),
            ]);
    }

    /**
     * The overview of one project: no files, no amounts.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 1, 'md' => 2, 'lg' => 3])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('reference')->label(__('erp.client_portal.project_number'))->fontFamily(FontFamily::Mono)->weight(FontWeight::Bold)->copyable(),
                    TextEntry::make('category.code')->label(__('erp.client_portal.category'))->formatStateUsing(fn (Project $record): string => $record->category?->label() ?? '')->badge()->color('gray'),
                    TextEntry::make('status')->label(__('erp.client_portal.status'))->badge(),
                    TextEntry::make('short_description')->label(__('erp.client_portal.description'))->placeholder('—')->columnSpanFull(),
                    TextEntry::make('cow_code')->label(__('erp.client_portal.cow_code'))->fontFamily(FontFamily::Mono)->placeholder('—'),
                    TextEntry::make('address')->label(__('erp.client_portal.address'))
                        ->state(fn (Project $record): ?string => $record->addressLine() ?? $record->workSite?->addressLine())->placeholder('—'),
                    TextEntry::make('planned_execution')->label(__('erp.client_portal.planned_execution'))
                        ->state(fn (Project $record): ?string => self::plannedExecution($record))->placeholder('—'),
                    TextEntry::make('battery_delivery')->label(__('erp.client_portal.battery_delivery'))
                        ->state(fn (Project $record): ?string => self::deliveryState($record))->badge()
                        ->color(fn (Project $record): string => self::openDeliveries($record)->isNotEmpty() ? 'warning' : 'success')
                        ->visible(fn (Project $record): bool => (string) $record->category?->code === BatteryReports::CATEGORY),
                    TextEntry::make('po_numbers')->label(__('erp.client_portal.po_numbers'))
                        ->state(fn (Project $record): array => $record->poNumbers->pluck('number')->all())->fontFamily(FontFamily::Mono)->listWithLineBreaks()->placeholder('—'),
                ]),
            Section::make(__('erp.gpw.short'))
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('gpw_requests')
                        ->hiddenLabel()
                        ->state(fn (Project $record): array => $record->gpwRequests->map(fn (GpwRequest $gpw): array => [
                            'period' => $gpw->period(),
                            'status' => $gpw->status->getLabel(),
                            'decided' => $gpw->decided_at ? trim($gpw->decided_at->format('d/m/Y').' · '.$gpw->decided_by_name, ' ·') : null,
                        ])->all())
                        ->columns(3)
                        ->schema([
                            TextEntry::make('period')->label(__('erp.gpw.period')),
                            TextEntry::make('status')->label(__('erp.client_portal.status'))->badge()->color('gray'),
                            TextEntry::make('decided')->label(__('erp.gpw.decision'))->placeholder('—'),
                        ])
                        ->placeholder(__('erp.client_portal.no_gpw')),
                ]),
            Section::make(__('erp.client_portal.project_leaders'))
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('project_leaders')
                        ->hiddenLabel()
                        ->state(fn (Project $record): array => $record->leaders()->where('is_active', true)->orderBy('name')->get()
                            ->map(fn (User $leader): array => ['name' => $leader->name, 'email' => $leader->email])->all())
                        ->columns(2)
                        ->schema([
                            TextEntry::make('name')->label(__('erp.fields.name')),
                            TextEntry::make('email')->label(__('erp.fields.email'))->url(fn (?string $state): ?string => $state ? 'mailto:'.$state : null)->copyable()->placeholder('—'),
                        ])
                        ->placeholder(__('erp.client_portal.no_leaders')),
                ]),
        ]);
    }

    /**
     * The battery deliveries of the project that are not delivered yet.
     *
     * @return Collection<int, BatteryDelivery>
     */
    public static function openDeliveries(Project $project): Collection
    {
        return $project->batteryDeliveries->reject(fn (BatteryDelivery $delivery): bool => (bool) $delivery->delivered)->values();
    }

    /**
     * 604 only: "To be delivered · 12/11/2026" (the first expected date), "Delivered" once all are, or null.
     */
    public static function deliveryState(Project $project): ?string
    {
        if ((string) $project->category?->code !== BatteryReports::CATEGORY || $project->batteryDeliveries->isEmpty()) {
            return null;
        }

        $open = self::openDeliveries($project);

        if ($open->isEmpty()) {
            return __('erp.client_portal.delivered_state');
        }

        $date = $open->pluck('planned_delivery_date')->filter()->sort()->first();

        return trim(__('erp.client_portal.to_be_delivered').($date ? ' · '.$date->format('d/m/Y') : ''));
    }

    /**
     * "12/11/2026 – 14/11/2026": the first and the last day of the project in the planning.
     */
    public static function plannedExecution(Project $project): ?string
    {
        $from = $project->planning_items_min_date ?? $project->planningItems()->min('date');
        $until = $project->planning_items_max_date ?? $project->planningItems()->max('date');

        if (blank($from)) {
            return null;
        }

        $from = Carbon::parse($from)->format('d/m/Y');
        $until = Carbon::parse($until)->format('d/m/Y');

        return $from === $until ? $from : "{$from} – {$until}";
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientProjects::route('/'),
            'view' => Pages\ViewClientProject::route('/{record}'),
        ];
    }
}
