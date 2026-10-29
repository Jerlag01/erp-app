<?php

namespace App\Filament\Resources\GpwRequests;

use App\Actions\Gpw\AddManualGpw;
use App\Actions\Gpw\DecideGpw;
use App\Enums\GpwStatus;
use App\Enums\NavigationGroup;
use App\Filament\Resources\GpwRequests\Pages\ListGpwRequests;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\GpwRequest;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
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
 * "GPW-aanvragen": every GPW request of the projects the user sees, with its period and where it
 * stands (waiting for approval / to be planned / on hold). Requests asked through a personal mail are
 * added here by hand, with their status; a waiting request's decision can be recorded here too.
 */
class GpwRequestResource extends Resource
{
    protected static ?string $model = GpwRequest::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'gpw-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Projects;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('erp.gpw.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.gpw.overview');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && modules()->projects() && $user->hasPermission('projects.gpw');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::canViewAny() ? static::getEloquentQuery()->where('status', GpwStatus::Pending)->count() : 0;

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('erp.gpw.overview_status.pending');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()->whereHas('project', fn (Builder $query) => $user ? $query->visibleTo($user) : $query->whereRaw('1 = 0'));
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('erp.gpw.overview_status_label'))
            ->formatStateUsing(fn (GpwRequest $record): string => $record->overviewStatus())
            ->badge()
            ->color(fn (GpwRequest $record): string => $record->status->getColor())
            ->icon(fn (GpwRequest $record): Heroicon => $record->status->getIcon());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.workSite', 'project.category', 'requester', 'team']))
            ->defaultSort('starts_on', 'desc')
            ->emptyStateHeading(__('erp.gpw.none'))
            ->columns([
                TextColumn::make('project.reference')
                    ->label(__('erp.gpw.project'))
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->description(fn (GpwRequest $record): ?string => $record->project?->subtitle())
                    ->url(fn (GpwRequest $record): ?string => $record->project && Gate::allows('view', $record->project) ? ProjectResource::getUrl('view', ['record' => $record->project]) : null)
                    ->searchable(),
                TextColumn::make('project.workSite.cow_code')->label(__('erp.client_portal.cow_code'))->fontFamily(FontFamily::Mono)->placeholder('—')->searchable(),
                TextColumn::make('starts_on')->label(__('erp.gpw.starts_on'))->date('d/m/Y')->sortable(),
                TextColumn::make('ends_on')->label(__('erp.gpw.ends_on'))->date('d/m/Y')->sortable(),
                self::statusColumn(),
                TextColumn::make('contact_name')
                    ->label(__('erp.gpw.sent_to'))
                    ->description(fn (GpwRequest $record): ?string => $record->contact_email)
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('source')
                    ->label(__('erp.gpw.source'))
                    ->formatStateUsing(fn (string $state): string => __("erp.gpw.sources.{$state}"))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('requester.name')->label(__('erp.gpw.requested_by'))->description(fn (GpwRequest $record): string => $record->created_at->format('d/m/Y'))->placeholder('—')->toggleable(),
                TextColumn::make('decided_by_name')
                    ->label(__('erp.gpw.decision'))
                    ->description(fn (GpwRequest $record): ?string => $record->decided_at ? $record->decided_at->format('d/m/Y').(filled($record->decision_note) ? ' · '.$record->decision_note : '') : null)
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('team.name')->label(__('erp.gpw.team'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('erp.resources.project_category.singular'))
                    ->options(fn (): array => ProjectCategory::query()->orderBy('code')->pluck('code', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $id) => $query->whereHas('project', fn (Builder $query) => $query->where('project_category_id', $id)))),
                SelectFilter::make('source')
                    ->label(__('erp.gpw.source'))
                    ->options([GpwRequest::SOURCE_MAIL => __('erp.gpw.sources.mail'), GpwRequest::SOURCE_MANUAL => __('erp.gpw.sources.manual')]),
            ])
            ->recordActions([
                // The answer came by personal mail or phone: record it here.
                Action::make('decide')
                    ->label(__('erp.gpw.record_decision'))
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('gray')
                    ->visible(fn (GpwRequest $record): bool => $record->isPending() && $record->project !== null && Gate::allows('manageGpw', $record->project))
                    ->authorize(fn (GpwRequest $record): bool => $record->isPending() && $record->project !== null && Gate::allows('manageGpw', $record->project))
                    ->modalDescription(__('erp.gpw.record_decision_help'))
                    ->fillForm(fn (GpwRequest $record): array => ['decided_by_name' => $record->contact_name])
                    ->schema([
                        ToggleButtons::make('decision')
                            ->label(__('erp.gpw.decision'))
                            ->options([GpwStatus::Approved->value => __('erp.gpw.overview_status.approved'), GpwStatus::Rejected->value => __('erp.gpw.overview_status.rejected')])
                            ->colors([GpwStatus::Approved->value => 'success', GpwStatus::Rejected->value => 'danger'])
                            ->inline()
                            ->required(),
                        TextInput::make('decided_by_name')->label(__('erp.gpw.decided_by'))->required()->maxLength(150),
                        Textarea::make('decision_note')->label(__('erp.gpw.decision_note'))->rows(2)->maxLength(2000),
                    ])
                    ->action(function (GpwRequest $record, array $data, DecideGpw $decide): void {
                        $data['decision'] === GpwStatus::Approved->value
                            ? $decide->approve($record, $data['decided_by_name'], note: $data['decision_note'] ?? null)
                            : $decide->reject($record, $data['decided_by_name'], note: $data['decision_note'] ?? null);

                        Notification::make()->title(__('erp.gpw.decision_saved'))->success()->send();
                    }),
            ]);
    }

    /**
     * The form of a GPW request asked through a personal mail.
     *
     * @return list<mixed>
     */
    public static function manualSchema(): array
    {
        $decided = fn (Get $get): bool => in_array($get('status'), [GpwStatus::Approved->value, GpwStatus::Rejected->value], true);

        return [
            Select::make('project_id')
                ->label(__('erp.gpw.project'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Project::query()
                    ->visibleTo(auth()->user())
                    ->whereNull('archived_at')
                    ->where(fn (Builder $query) => $query->where('reference', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('cow_code', 'like', '%'.addcslashes($search, '%_\\').'%'))
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (Project $project): array => [$project->id => trim($project->reference.' — '.$project->subtitle(), ' —')])
                    ->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => Project::query()->find($value)?->reference)
                ->required(),
            Grid::make(2)->schema([
                DatePicker::make('starts_on')->label(__('erp.gpw.starts_on'))->native(false)->displayFormat('d/m/Y')->required()->live(),
                DatePicker::make('ends_on')->label(__('erp.gpw.ends_on'))->native(false)->displayFormat('d/m/Y')->afterOrEqual('starts_on')->required(),
            ]),
            ToggleButtons::make('status')
                ->label(__('erp.gpw.overview_status_label'))
                ->options([
                    GpwStatus::Pending->value => __('erp.gpw.overview_status.pending'),
                    GpwStatus::Approved->value => __('erp.gpw.overview_status.approved'),
                    GpwStatus::Rejected->value => __('erp.gpw.overview_status.rejected'),
                ])
                ->colors([GpwStatus::Pending->value => 'warning', GpwStatus::Approved->value => 'success', GpwStatus::Rejected->value => 'danger'])
                ->default(GpwStatus::Pending->value)
                ->required()
                ->live(),
            Grid::make(2)->schema([
                TextInput::make('contact_name')->label(__('erp.gpw.manual_contact'))->helperText(__('erp.gpw.manual_contact_help'))->required()->maxLength(150),
                TextInput::make('contact_email')->label(__('erp.fields.email'))->email()->maxLength(150),
            ]),
            Grid::make(2)->visible($decided)->schema([
                TextInput::make('decided_by_name')->label(__('erp.gpw.decided_by'))->helperText(__('erp.gpw.decided_by_help'))->maxLength(150),
                Textarea::make('decision_note')->label(__('erp.gpw.decision_note'))->rows(2)->maxLength(2000),
            ]),
            Textarea::make('remarks')->label(__('erp.gpw.remarks'))->rows(3)->maxLength(2000),
        ];
    }

    public static function addManual(array $data): GpwRequest
    {
        return app(AddManualGpw::class)->handle($data, auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGpwRequests::route('/'),
        ];
    }
}
