<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Actions\Gpw\PlanGpw;
use App\Actions\Gpw\RequestGpw;
use App\Enums\GpwStatus;
use App\Filament\Resources\GpwRequests\GpwRequestResource;
use App\Models\GpwRequest;
use App\Models\Project;
use App\Models\Team;
use App\Support\Planning;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The GPW requests of a project: period, who it went to, the client's decision, and — once approved —
 * planning a team in that period. Requests are made with the "GPW aanvragen" button on the project.
 */
class GpwRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'gpwRequests';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedCalendarDateRange;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.gpw.title');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var Project $ownerRecord */
        $count = $ownerRecord->gpwRequests()->whereIn('status', [GpwStatus::Pending, GpwStatus::Approved])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Project $ownerRecord */
        return Gate::allows('view', $ownerRecord) && (Gate::allows('requestGpw', $ownerRecord) || $ownerRecord->gpwRequests()->exists());
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('erp.gpw.none'))
            ->columns([
                TextColumn::make('starts_on')->label(__('erp.gpw.period'))->state(fn (GpwRequest $record): string => $record->period())->sortable(),
                GpwRequestResource::statusColumn(),
                TextColumn::make('contact_name')->label(__('erp.gpw.sent_to'))->description(fn (GpwRequest $record): string => $record->contact_email.' · '.strtoupper($record->locale)),
                TextColumn::make('requester.name')->label(__('erp.gpw.requested_by'))->description(fn (GpwRequest $record): string => $record->created_at->format('d/m/Y H:i')),
                TextColumn::make('remarks')->label(__('erp.gpw.remarks'))->limit(60)->tooltip(fn (GpwRequest $record): ?string => $record->remarks)->placeholder('—')->toggleable(),
                TextColumn::make('decided_by_name')
                    ->label(__('erp.gpw.decision'))
                    ->description(fn (GpwRequest $record): ?string => $record->decided_at ? $record->decided_at->format('d/m/Y H:i').(filled($record->decision_note) ? ' · '.$record->decision_note : '') : null)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('team.name')->label(__('erp.gpw.team'))->placeholder('—'),
            ])
            ->recordActions([
                Action::make('plan')
                    ->label(__('erp.gpw.plan'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('primary')
                    ->visible(fn (GpwRequest $record): bool => $record->status === GpwStatus::Approved && $record->canBePlannedBy(auth()->user()) && app(Planning::class)->enabled())
                    ->modalHeading(fn (GpwRequest $record): string => __('erp.gpw.plan_heading', ['period' => $record->period()]))
                    ->modalDescription(__('erp.gpw.plan_help'))
                    ->fillForm(function (GpwRequest $record): array {
                        $slot = app(Planning::class)->slots()[0] ?? null;

                        return [
                            'date' => $record->starts_on->toDateString(),
                            'until' => $record->ends_on->toDateString(),
                            'start_time' => $slot['start'] ?? '07:30',
                            'end_time' => $slot['end'] ?? '16:00',
                            'notes' => $record->remarks,
                        ];
                    })
                    ->schema(fn (GpwRequest $record): array => [
                        Select::make('team_id')
                            ->label(__('erp.gpw.team'))
                            ->options(fn (): array => Team::options(Team::query()->where('is_active', true)))
                            ->searchable()
                            ->required(),
                        Grid::make(3)->schema([
                            DatePicker::make('date')->label(__('erp.planning.from'))->native(false)->displayFormat('d/m/Y')->minDate($record->starts_on)->maxDate($record->ends_on)->required(),
                            DatePicker::make('until')->label(__('erp.planning.until'))->native(false)->displayFormat('d/m/Y')->minDate($record->starts_on)->maxDate($record->ends_on)->afterOrEqual('date')->required(),
                            Toggle::make('include_weekends')->label(__('erp.planning.include_weekends'))->inline(false),
                        ]),
                        Grid::make(3)->schema([
                            Select::make('slot')
                                ->label(__('erp.planning.slot'))
                                ->placeholder(__('erp.planning.custom_slot'))
                                ->options(collect(app(Planning::class)->slots())->map(fn (array $slot): string => "{$slot['label']} ({$slot['start']}–{$slot['end']})")->all())
                                ->visible(app(Planning::class)->slots() !== [])
                                ->dehydrated(false)
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    $slots = app(Planning::class)->slots();

                                    if (isset($slots[$state])) {
                                        $set('start_time', $slots[$state]['start']);
                                        $set('end_time', $slots[$state]['end']);
                                    }
                                }),
                            TimePicker::make('start_time')->label(__('erp.planning.start'))->seconds(false)->required(),
                            TimePicker::make('end_time')->label(__('erp.planning.end'))->seconds(false)->required()->after('start_time'),
                        ]),
                        Textarea::make('notes')->label(__('erp.planning.notes'))->rows(2),
                        Toggle::make('notify_mail')->label(__('erp.planning.notify_mail'))->default(true),
                    ])
                    ->action(function (GpwRequest $record, array $data, PlanGpw $plan): void {
                        $result = $plan->handle($record, auth()->user(), $data);

                        Notification::make()
                            ->title(__('erp.gpw.planned', ['count' => count($result['created'])]))
                            ->body($result['skipped'] !== [] ? implode("\n", array_slice($result['skipped'], 0, 8)) : null)
                            ->{$result['skipped'] === [] ? 'success' : 'warning'}()
                            ->send();
                    }),
                Action::make('resend')
                    ->label(__('erp.gpw.resend'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->visible(fn (GpwRequest $record): bool => $record->isPending() && ! $record->isManual() && Gate::allows('requestGpw', $this->getOwnerRecord()))
                    ->requiresConfirmation()
                    ->modalDescription(fn (GpwRequest $record): string => __('erp.gpw.resend_help', ['email' => $record->contact_email]))
                    ->action(function (GpwRequest $record, RequestGpw $requests): void {
                        $requests->mail($record)
                            ? Notification::make()->title(__('erp.gpw.sent', ['email' => $record->contact_email]))->success()->send()
                            : Notification::make()->title(__('erp.gpw.mail_failed'))->warning()->send();
                    }),
                Action::make('cancel')
                    ->label(__('erp.gpw.cancel'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (GpwRequest $record): bool => $record->isPending() && ($record->requested_by === auth()->id() || Gate::allows('update', $this->getOwnerRecord())))
                    ->requiresConfirmation()
                    ->action(fn (GpwRequest $record) => $record->update(['status' => GpwStatus::Cancelled]))
                    ->successNotificationTitle(__('erp.gpw.cancelled')),
            ]);
    }
}
