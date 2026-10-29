<?php

namespace App\Filament\Pages;

use App\Actions\Planning\SavePlanningItems;
use App\Enums\NavigationGroup;
use App\Enums\PlanningKind;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Holiday;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\Planning as PlanningRules;
use App\Support\VacationCalendar;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Everyone's own planning calendar: items in timeslots per day, as a week (one row per person),
 * a month or a simple list. Rights and blocked days: see App\Support\Planning.
 */
class Planning extends Page
{
    protected string $view = 'filament.pages.planning';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Planning;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'planning';

    /** calendar or list */
    #[Url]
    public string $display = 'calendar';

    /** week or month */
    #[Url]
    public string $period = 'week';

    /** Any day in the period shown (Y-m-d). */
    #[Url]
    public ?string $date = null;

    #[Url]
    public ?int $team = null;

    #[Url]
    public ?int $person = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $planning = app(PlanningRules::class);

        // Without a prestation sheet (e.g. HR) nobody plans them: only for those who plan or see everyone.
        return $planning->enabled() && $user !== null && ($user->hasTimesheet() || $planning->canPlan($user) || $planning->seesEveryone($user));
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.planning.title');
    }

    public function getTitle(): string
    {
        return __('erp.planning.title');
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->display = in_array($this->display, ['calendar', 'list'], true) ? $this->display : 'calendar';
        $this->period = in_array($this->period, ['week', 'month'], true) ? $this->period : 'week';

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->date) || ! strtotime((string) $this->date)) {
            $this->date = today()->toDateString();
        }

        $this->updatedTeam($this->team);
        $this->updatedPerson($this->person);
    }

    public function previous(): void
    {
        $this->date = ($this->period === 'month' ? $this->anchor()->subMonthNoOverflow() : $this->anchor()->subWeek())->toDateString();
    }

    public function next(): void
    {
        $this->date = ($this->period === 'month' ? $this->anchor()->addMonthNoOverflow() : $this->anchor()->addWeek())->toDateString();
    }

    public function today(): void
    {
        $this->date = today()->toDateString();
    }

    public function setDisplay(string $display): void
    {
        $this->display = $display === 'list' ? 'list' : 'calendar';
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period === 'month' ? 'month' : 'week';
    }

    public function updatedTeam(mixed $value): void
    {
        $this->team = filled($value) && array_key_exists((int) $value, $this->teamOptions()) ? (int) $value : null;
    }

    public function updatedPerson(mixed $value): void
    {
        $this->person = filled($value) && array_key_exists((int) $value, $this->personOptions()) ? (int) $value : null;
    }

    /**
     * The employee ticks a task of their item off (or on again).
     */
    public function toggleTask(int $itemId, int $index): void
    {
        $item = PlanningItem::query()->findOrFail($itemId);
        abort_unless(auth()->user()->can('tickTasks', $item), 403);

        $tasks = $item->taskList();

        if (isset($tasks[$index])) {
            $tasks[$index]['done'] = ! $tasks[$index]['done'];
            $item->update(['tasks' => $tasks]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function teamOptions(): array
    {
        if (! modules()->teams()) {
            return [];
        }

        return Team::options(Team::query()->visibleTo($this->user())->where('is_active', true));
    }

    /**
     * People whose planning the user may see (active accounts).
     *
     * @return array<int, string>
     */
    public function personOptions(): array
    {
        $ids = $this->rules()->visibleUserIds($this->user());

        return User::query()->active()->withTimesheet()
            ->when($ids !== null, fn (Builder $query) => $query->whereKey($ids))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $viewer = $this->user();
        [$from, $until] = $this->range();
        // The month calendar shows whole weeks.
        [$gridFrom, $gridUntil] = $this->period === 'month' && $this->display === 'calendar'
            ? [$from->startOfWeek(CarbonImmutable::MONDAY), $until->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()]
            : [$from, $until];

        $people = $this->people();
        $items = PlanningItem::query()
            ->visibleTo($viewer)
            ->between($gridFrom, $gridUntil)
            ->when($this->person || $this->team, fn (Builder $query) => $query->whereIn('user_id', $people->keys()))
            ->with(['user', 'project.category', 'project.leaders', 'project.batteryDossier', 'project.baeOrder'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Project leaders see items of people outside their view on their projects: show those people too.
        $people = $people->union($items->pluck('user')->keyBy('id'))->sortBy(fn (User $user): string => ($user->id === $viewer->id ? '0' : '1').mb_strtolower($user->name));
        $blocked = $this->rules()->blockedDays($people->keys()->all(), $gridFrom, $gridUntil, $viewer);
        // Only sickness and family leave close a day; vacation, IR days … only show.
        $closed = $this->rules()->closedDays($people->keys()->all(), $gridFrom, $gridUntil, $viewer);
        $holidays = Holiday::query()->whereBetween('date', [$gridFrom->toDateString(), $gridUntil->toDateString()])->get()
            ->mapWithKeys(fn (Holiday $holiday): array => [$holiday->date->toDateString() => $holiday->label()]);

        $days = collect(CarbonPeriod::create($gridFrom, $gridUntil))->map(fn ($day): CarbonImmutable => CarbonImmutable::instance($day))->values();
        $entries = $items->map(fn (PlanningItem $item): array => $this->entry($item, $viewer, $closed));

        return [
            'from' => $from,
            'until' => $until,
            'days' => $days,
            'people' => $people->values(),
            'entries' => $entries,
            'byPersonDay' => $entries->groupBy(fn (array $entry): string => $entry['item']->user_id.'|'.$entry['item']->date->toDateString()),
            'byDay' => $entries->groupBy(fn (array $entry): string => $entry['item']->date->toDateString()),
            'blocked' => $blocked,
            'closed' => $closed,
            'holidays' => $holidays,
            'canPlan' => $this->rules()->canPlan($viewer),
            'teams' => $this->teamOptions(),
            'persons' => $this->personOptions(),
            'colors' => $people->mapWithKeys(fn (User $user): array => [$user->id => app(VacationCalendar::class)->colorFor($user->id)]),
            'kinds' => PlanningKind::cases(),
            'heading' => $this->heading($from, $until),
        ];
    }

    /**
     * What the views need per item.
     *
     * @param  array<int, array<string, string>>  $blocked
     * @return array{item: PlanningItem, url: ?string, canManage: bool, canTick: bool, conflict: ?string, color: string}
     */
    protected function entry(PlanningItem $item, User $viewer, array $blocked): array
    {
        return [
            'item' => $item,
            // Clicking a project item opens the project.
            'url' => $item->kind === PlanningKind::Project && $item->project && $viewer->can('view', $item->project)
                ? ProjectResource::getUrl('view', ['record' => $item->project], panel: filament()->getCurrentPanel()?->getId() ?? 'app')
                : null,
            'canManage' => $this->rules()->canManage($viewer, $item),
            'canTick' => $this->rules()->canTickTasks($viewer, $item),
            // An absence or vacation that was added after the item was planned.
            'conflict' => $blocked[$item->user_id][$item->date->toDateString()] ?? null,
            'color' => $item->kind->hex(),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    protected function people(): Collection
    {
        $ids = $this->rules()->visibleUserIds($this->user());

        return User::query()->active()->withTimesheet()
            ->when($ids !== null, fn (Builder $query) => $query->whereKey($ids))
            ->when($this->person, fn (Builder $query, int $person) => $query->whereKey($person))
            ->when($this->team, fn (Builder $query, int $team) => $query->whereHas('teams', fn (Builder $query) => $query->whereKey($team)))
            ->orderBy('name')
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function range(): array
    {
        $anchor = $this->anchor();

        return $this->period === 'month'
            ? [$anchor->startOfMonth(), $anchor->endOfMonth()->startOfDay()]
            : [$anchor->startOfWeek(CarbonImmutable::MONDAY), $anchor->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay()];
    }

    protected function heading(CarbonImmutable $from, CarbonImmutable $until): string
    {
        $locale = app()->getLocale();

        return $this->period === 'month'
            ? $from->locale($locale)->translatedFormat('F Y')
            : __('erp.planning.week', ['week' => $from->isoWeek(), 'from' => $from->format('d/m'), 'until' => $until->format('d/m/Y')]);
    }

    protected function anchor(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date ?? today()->toDateString())->startOfDay();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->shareAction(),
            $this->createItemAction(),
        ];
    }

    public function createItemAction(): Action
    {
        return Action::make('createItem')
            ->label(__('erp.planning.add'))
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => $this->rules()->canPlan($this->user()))
            ->modalHeading(__('erp.planning.add'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->fillForm(fn (array $arguments): array => [
                'user_ids' => array_values(array_filter([(int) ($arguments['user'] ?? 0) ?: $this->person])),
                'date' => $arguments['date'] ?? $this->date,
                'kind' => $this->rules()->kindsFor($this->user())[0]->value ?? null,
                ...$this->firstSlot(),
                'notify_mail' => (bool) settings()->get('planning.mail_default', true),
            ])
            ->schema(fn (): array => $this->itemForm(null))
            ->action(function (array $data, Action $action): void {
                abort_unless($this->rules()->canPlan($this->user()), 403);

                $saver = app(SavePlanningItems::class);
                $result = $this->withFormErrors(fn (): array => $saver->create($data, $this->user()));

                $notification = Notification::make()
                    ->title(trans_choice('erp.planning.created', count($result['created']), ['count' => count($result['created'])]))
                    ->success();

                if ($result['skipped'] !== []) {
                    $notification->warning()->body(new HtmlString(e(__('erp.planning.skipped')).'<br>'.collect($result['skipped'])->take(10)->map(fn (string $line): string => e($line))->implode('<br>')))->persistent();
                }

                $notification->send();
                $this->mailFailedNotice($saver);
            });
    }

    public function editItemAction(): Action
    {
        return Action::make('editItem')
            ->modalHeading(__('erp.planning.edit'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->fillForm(function (array $arguments): array {
                $item = $this->itemFrom($arguments, 'update');

                return [
                    'user_id' => $item->user_id,
                    'date' => $item->date->toDateString(),
                    'start_time' => $item->startsAt(),
                    'end_time' => $item->endsAt(),
                    'kind' => $item->kind->value,
                    'project_id' => $item->project_id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'tasks' => array_column($item->taskList(), 'text'),
                    'notes' => $item->notes,
                    'notify_mail' => (bool) settings()->get('planning.mail_default', true),
                ];
            })
            ->schema(fn (array $arguments): array => $this->itemForm($this->itemFrom($arguments, 'update')))
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('deleteInstead', ['delete' => true])
                    ->label(__('erp.actions.delete'))
                    ->color('danger')
                    ->requiresConfirmation(),
            ])
            ->action(function (array $data, array $arguments): void {
                $item = $this->itemFrom($arguments, 'update');
                $saver = app(SavePlanningItems::class);

                if ($arguments['delete'] ?? false) {
                    $saver->delete($item, $this->user(), (bool) ($data['notify_mail'] ?? false));
                    Notification::make()->title(__('erp.planning.deleted'))->success()->send();
                } else {
                    $this->withFormErrors(fn (): PlanningItem => $saver->update($item, $data, $this->user()));
                    Notification::make()->title(__('erp.planning.saved'))->success()->send();
                }

                $this->mailFailedNotice($saver);
            });
    }

    public function viewItemAction(): Action
    {
        return Action::make('viewItem')
            ->modalHeading(fn (array $arguments): string => $this->itemFrom($arguments, 'view')->topic())
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('erp.actions.close'))
            ->schema(fn (array $arguments): array => $this->itemDetails($this->itemFrom($arguments, 'view')))
            ->extraModalFooterActions(fn (array $arguments): array => array_values(array_filter([
                ($url = $this->entry($item = $this->itemFrom($arguments, 'view'), $this->user(), [])['url'])
                    ? Action::make('openProject')->label(__('erp.planning.open_project'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url($url)
                    : null,
                $this->rules()->canManage($this->user(), $item)
                    ? Action::make('editFromView')->label(__('erp.actions.edit'))->color('gray')->icon(Heroicon::OutlinedPencilSquare)
                        ->action(fn () => $this->replaceMountedAction('editItem', ['item' => $item->id]))
                    : null,
            ])));
    }

    public function shareAction(): Action
    {
        return Action::make('share')
            ->label(__('erp.planning.share'))
            ->icon(Heroicon::OutlinedShare)
            ->color('gray')
            ->modalHeading(__('erp.planning.share'))
            ->modalDescription(__('erp.planning.share_help'))
            ->fillForm(fn (): array => ['viewers' => $this->user()->planningViewers()->pluck('users.id')->all()])
            ->schema([
                Select::make('viewers')
                    ->label(__('erp.planning.share_with'))
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => User::query()->active()->whereKeyNot($this->user()->id)->orderBy('name')->pluck('name', 'id')->all()),
                Text::make(fn (): string => __('erp.planning.shared_with_me', [
                    'names' => User::query()->whereIn('id', fn ($query) => $query->select('user_id')->from('planning_shares')->where('viewer_id', $this->user()->id))->orderBy('name')->pluck('name')->implode(', ') ?: '—',
                ])),
            ])
            ->action(function (array $data): void {
                $ids = User::query()->active()->whereKey((array) ($data['viewers'] ?? []))->whereKeyNot($this->user()->id)->pluck('id')->all();
                $this->user()->planningViewers()->sync($ids);

                Notification::make()->title(__('erp.planning.share_saved'))->success()->send();
            });
    }

    /**
     * @return array<Component>
     */
    protected function itemForm(?PlanningItem $item): array
    {
        $viewer = $this->user();
        $kinds = $this->rules()->kindsFor($viewer);

        if ($item && ! in_array($item->kind, $kinds, true)) {
            $kinds[] = $item->kind;
        }

        $slots = $this->rules()->slots();
        $isKind = fn (PlanningKind ...$options): \Closure => fn (Get $get): bool => in_array(PlanningKind::tryFrom((string) $get('kind')), $options, true);

        return [
            $item === null
                ? Select::make('user_ids')
                    ->label(__('erp.planning.employees'))
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->options(fn (): array => User::query()->active()->withTimesheet()->orderBy('name')->pluck('name', 'id')->all())
                : Select::make('user_id')
                    ->label(__('erp.planning.employee'))
                    ->required()
                    ->searchable()
                    ->options(fn (): array => User::query()->active()->withTimesheet()->orderBy('name')->pluck('name', 'id')->all()),
            Grid::make(3)->schema(array_values(array_filter([
                DatePicker::make('date')->label($item === null ? __('erp.planning.from') : __('erp.fields.date'))->required()->native(false)->displayFormat('d/m/Y')->live(),
                $item === null
                    ? DatePicker::make('until')->label(__('erp.planning.until'))->helperText(__('erp.planning.until_help'))->native(false)->displayFormat('d/m/Y')->afterOrEqual('date')->live()
                    : null,
                $item === null
                    ? Toggle::make('include_weekends')->label(__('erp.planning.include_weekends'))->inline(false)->visible(fn (Get $get): bool => filled($get('until')) && $get('until') !== $get('date'))
                    : null,
            ]))),
            Grid::make(3)->schema([
                Select::make('slot')
                    ->label(__('erp.planning.slot'))
                    ->placeholder(__('erp.planning.custom_slot'))
                    ->options(collect($slots)->map(fn (array $slot): string => "{$slot['label']} ({$slot['start']}–{$slot['end']})")->all())
                    ->visible($slots !== [])
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set) use ($slots): void {
                        if (isset($slots[$state])) {
                            $set('start_time', $slots[$state]['start']);
                            $set('end_time', $slots[$state]['end']);
                        }
                    }),
                TimePicker::make('start_time')->label(__('erp.planning.start'))->seconds(false)->required(),
                TimePicker::make('end_time')->label(__('erp.planning.end'))->seconds(false)->required()->after('start_time'),
            ]),
            ToggleButtons::make('kind')
                ->label(__('erp.planning.kind'))
                ->options(collect($kinds)->mapWithKeys(fn (PlanningKind $kind): array => [$kind->value => $kind->getLabel()])->all())
                ->icons(collect($kinds)->mapWithKeys(fn (PlanningKind $kind): array => [$kind->value => $kind->getIcon()])->all())
                ->inline()
                ->required()
                ->live(),
            Select::make('project_id')
                ->label(__('erp.resources.project.singular'))
                ->helperText(__('erp.planning.open_projects_only'))
                ->searchable()
                ->options(fn (): array => $this->projectOptions($item))
                ->visible($isKind(PlanningKind::Project))
                ->required($isKind(PlanningKind::Project)),
            TextInput::make('title')
                ->label(fn (Get $get): string => $get('kind') === PlanningKind::Warehouse->value ? __('erp.planning.short_title') : __('erp.planning.title_field'))
                ->maxLength(255)
                ->visible($isKind(PlanningKind::Warehouse, PlanningKind::Other))
                ->required($isKind(PlanningKind::Other)),
            Textarea::make('description')
                ->label(__('erp.planning.description'))
                ->rows(3)
                ->maxLength(5000)
                ->visible($isKind(PlanningKind::Warehouse, PlanningKind::Other))
                ->required($isKind(PlanningKind::Warehouse)),
            Repeater::make('tasks')
                ->label(__('erp.planning.tasks'))
                ->simple(TextInput::make('text')->required()->maxLength(255))
                ->addActionLabel(__('erp.planning.add_task'))
                ->reorderable()
                ->minItems(fn (Get $get): int => $get('kind') === PlanningKind::Warehouse->value ? 1 : 0)
                ->maxItems(50)
                ->defaultItems(fn (Get $get): int => $get('kind') === PlanningKind::Warehouse->value ? 1 : 0)
                ->visible($isKind(PlanningKind::Warehouse, PlanningKind::Other)),
            Textarea::make('notes')->label(__('erp.fields.notes'))->rows(2)->maxLength(2000),
            Toggle::make('notify_mail')
                ->label(__('erp.planning.notify_mail'))
                ->helperText(fn (): string => __('erp.planning.notify_mail_help', ['addresses' => implode(', ', $this->rules()->notifyAddresses()) ?: '—'])),
        ];
    }

    /**
     * @return array<Component>
     */
    protected function itemDetails(PlanningItem $item): array
    {
        $canTick = $this->rules()->canTickTasks($this->user(), $item);
        $lines = array_filter([
            __('erp.planning.employee').': '.$item->user->name,
            $item->date->locale(app()->getLocale())->translatedFormat('l d/m/Y').' · '.$item->timeRange(),
            $item->kind->getLabel().($item->subtitle() ? ' · '.$item->subtitle() : ''),
            $item->batteries(),
            $item->kind === PlanningKind::Project ? $item->project?->addressLine() : null,
        ]);

        return array_values(array_filter([
            Text::make(new HtmlString(collect($lines)->map(fn (string $line): string => e($line))->implode('<br>'))),
            filled($item->description) ? Text::make(new HtmlString('<strong>'.e(__('erp.planning.description')).'</strong><br>'.nl2br(e($item->description)))) : null,
            $item->taskList() !== []
                ? Text::make(new HtmlString(view('filament.pages.planning-tasks', ['item' => $item, 'canTick' => $canTick])->render()))
                : null,
            filled($item->notes) ? Text::make(new HtmlString('<strong>'.e(__('erp.fields.notes')).'</strong><br>'.nl2br(e($item->notes)))) : null,
        ]));
    }

    /**
     * Open projects the user may plan, plus the item's current project.
     *
     * @return array<int, string>
     */
    protected function projectOptions(?PlanningItem $item): array
    {
        return $this->rules()->plannableProjects($this->user())
            ->when($item?->project_id, fn (Builder $query, int $id) => $query->orWhere('projects.id', $id))
            ->with('category')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Project $project): array => [$project->id => trim($project->reference.' '.($project->subtitle() ? '— '.$project->subtitle() : ''))])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function itemFrom(array $arguments, string $ability): PlanningItem
    {
        $item = PlanningItem::query()->with(['user', 'project.category', 'project.batteryDossier', 'project.baeOrder'])->findOrFail((int) ($arguments['item'] ?? 0));
        abort_unless($this->user()->can($ability, $item), 403);

        return $item;
    }

    /**
     * Shows the errors of the save action on the fields of the open modal.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withFormErrors(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            $path = 'mountedActions.'.(count($this->mountedActions) - 1).'.data.';

            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => [$path.$key => $messages])->all());
        }
    }

    protected function mailFailedNotice(SavePlanningItems $saver): void
    {
        if ($saver->mailFailed) {
            Notification::make()->title(__('erp.planning.mail_failed'))->warning()->send();
        }
    }

    /**
     * @return array{start_time?: string, end_time?: string}
     */
    protected function firstSlot(): array
    {
        $slot = $this->rules()->slots()[0] ?? null;

        return $slot ? ['slot' => 0, 'start_time' => $slot['start'], 'end_time' => $slot['end']] : [];
    }

    protected function rules(): PlanningRules
    {
        return app(PlanningRules::class);
    }

    protected function user(): User
    {
        return auth()->user();
    }
}
