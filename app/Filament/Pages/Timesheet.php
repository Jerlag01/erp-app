<?php

namespace App\Filament\Pages;

use App\Actions\Timesheets\SaveTimesheetDay;
use App\Actions\Timesheets\SendWeeklyTimesheets;
use App\Enums\NavigationGroup;
use App\Models\Project;
use App\Models\TimesheetDay;
use App\Models\TimesheetWeek;
use App\Models\User;
use App\Support\PdfAccess;
use App\Support\Timesheets;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The prestation sheet, one day at a time: dates, name, licence plate and week come from the system;
 * the employee fills in odometer readings and times per row. Absences, vacation, trainings and public
 * holidays fill themselves in. Office staff with "timesheets.see_all" open any employee's sheet.
 *
 * @property-read Schema $form
 */
class Timesheet extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.timesheet';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'timesheet';

    #[Url]
    public ?string $date = null;

    #[Url]
    public ?int $employee = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        // Without a sheet of their own (e.g. HR) only to look at others.
        return modules()->timesheets() && $user !== null && ($user->hasTimesheet() || $user->isAdmin() || $user->hasPermission('timesheets.see_all'));
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.timesheets.title');
    }

    public function getTitle(): string
    {
        return __('erp.timesheets.title');
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $date = rescue(fn (): CarbonImmutable => CarbonImmutable::parse((string) $this->date), null, false) ?? today()->toImmutable();
        // Weekends belong to the week before: show its Friday.
        $this->date = ($date->isWeekend() ? $date->previous(CarbonImmutable::FRIDAY) : $date)->toDateString();

        if ($this->employee && ! app(Timesheets::class)->canView(auth()->user(), $this->employeeUser())) {
            $this->employee = null;
        }

        // No sheet of their own: open the first employee's.
        if (! $this->employee && ! auth()->user()->hasTimesheet()) {
            $this->employee = array_key_first($this->employeeOptions());
        }

        $this->fillForm();
    }

    public function employeeUser(): User
    {
        return $this->employee ? User::query()->findOrFail($this->employee) : auth()->user();
    }

    public function day(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date);
    }

    public function goTo(string $date): void
    {
        $day = rescue(fn (): CarbonImmutable => CarbonImmutable::parse($date), null, false);

        if ($day) {
            $this->date = ($day->isWeekend() ? $day->previous(CarbonImmutable::FRIDAY) : $day)->toDateString();
            $this->fillForm();
        }
    }

    public function previousWeek(): void
    {
        $this->goTo($this->day()->subWeek()->toDateString());
    }

    public function nextWeek(): void
    {
        $this->goTo($this->day()->addWeek()->toDateString());
    }

    public function updatedEmployee(mixed $value): void
    {
        $this->employee = filled($value) && array_key_exists((int) $value, $this->employeeOptions()) ? (int) $value : null;
        $this->fillForm();
    }

    /**
     * Employees whose sheet the user may open (empty when only their own).
     *
     * @return array<int, string>
     */
    public function employeeOptions(): array
    {
        $user = auth()->user();

        if (! $user->isAdmin() && ! $user->hasPermission('timesheets.see_all')) {
            return [];
        }

        return User::query()->active()->withTimesheet()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function weekRecord(): ?TimesheetWeek
    {
        return TimesheetWeek::query()->where('user_id', $this->employeeUser()->id)->whereDate('week_start', Timesheets::monday($this->day())->toDateString())->first();
    }

    public function canEdit(): bool
    {
        return app(Timesheets::class)->canEdit(auth()->user(), $this->employeeUser(), $this->weekRecord());
    }

    protected function fillForm(): void
    {
        $user = $this->employeeUser();
        $day = $this->day();
        $timesheets = app(Timesheets::class);
        $record = TimesheetDay::query()->where('user_id', $user->id)->whereDate('date', $day->toDateString())->first();
        $status = $timesheets->statuses($user, $day, $day)[$day->toDateString()] ?? null;

        $this->form->fill([
            'rows' => $record ? Timesheets::normaliseRows((array) $record->rows) : $timesheets->prefillRows($user, $day),
            'lunch_break' => ($status['lunch_locked'] ?? false) || ($record?->lunch_break ?? true),
            'remarks' => $record?->remarks,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $locked = fn (): bool => (bool) ($this->status()['lunch_locked'] ?? false);
        $fixed = app(Timesheets::class)->fixedDay($this->employeeUser());

        return $schema
            ->statePath('data')
            ->disabled(fn (): bool => ! $this->canEdit())
            ->components([
                Repeater::make('rows')
                    ->hiddenLabel()
                    ->reorderable(false)
                    ->addActionLabel(__('erp.timesheets.add_extra'))
                    // A new extra site goes just before the end of the day.
                    ->addAction(fn (Action $action): Action => $action
                        ->visible(fn (Repeater $component): bool => $this->canEdit() && collect($component->getRawState())->where('kind', 'extra')->count() < Timesheets::MAX_EXTRA)
                        ->action(function (Repeater $component): void {
                            $items = $component->getRawState();
                            $endKey = array_key_last($items);
                            $newKey = $component->generateUuid() ?? (string) str()->uuid();
                            $end = $items[$endKey];
                            unset($items[$endKey]);
                            $items[$newKey] = ['kind' => 'extra'];
                            $items[$endKey] = $end;

                            $component->rawState($items);
                            $component->getChildSchema($newKey)->fill(['kind' => 'extra']);
                            $component->callAfterStateUpdated();
                        }))
                    // Only extra sites can be removed.
                    ->deleteAction(fn (Action $action): Action => $action->hidden(fn (array $arguments, Repeater $component): bool => ($component->getRawState()[$arguments['item'] ?? '']['kind'] ?? null) !== 'extra'))
                    // A "bediende" registers no kilometres and no times (fixed days of 8 h).
                    ->table([
                        Repeater\TableColumn::make(''),
                        ...($fixed ? [] : [
                            Repeater\TableColumn::make(__('erp.timesheets.km_departure')),
                            Repeater\TableColumn::make(__('erp.timesheets.km_arrival')),
                            Repeater\TableColumn::make(__('erp.timesheets.arrival')),
                            Repeater\TableColumn::make(__('erp.timesheets.departure')),
                        ]),
                        Repeater\TableColumn::make(__('erp.timesheets.imputation')),
                        Repeater\TableColumn::make(__('erp.timesheets.description')),
                    ])
                    ->schema([
                        Text::make(fn (Get $get): string => __('erp.timesheets.kinds.'.$get('kind'))),
                        ...($fixed ? [] : [
                            TextInput::make('km_departure')->hiddenLabel()->integer()->minValue(0)->maxValue(9_999_999)->live(onBlur: true),
                            TextInput::make('km_arrival')->hiddenLabel()->integer()->minValue(0)->maxValue(9_999_999)->live(onBlur: true)
                                ->rule(fn (Get $get) => filled($get('km_departure')) ? 'gte:'.(int) $get('km_departure') : null),
                            TextInput::make('arrival')->hiddenLabel()->type('time')->live(onBlur: true),
                            TextInput::make('departure')->hiddenLabel()->type('time')->live(onBlur: true)
                                ->rule(fn (Get $get) => filled($get('arrival')) ? 'after_or_equal:'.$get('arrival') : null),
                        ]),
                        Select::make('project_id')
                            ->hiddenLabel()
                            ->placeholder(__('erp.timesheets.imputation_placeholder'))
                            ->searchable()
                            ->options(fn (): array => $this->plannedProjectOptions())
                            ->getSearchResultsUsing(fn (string $search): array => $this->projectSearch($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => ($project = Project::query()->with('category')->find($value)) ? Timesheets::imputation($project) : null)
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $project = Project::query()->with(['category', 'workSite'])->find($state);
                                $set('imputation', $project ? Timesheets::imputation($project) : null);
                                $set('description', $project ? ($project->workSite?->cow_code ?? $project->cow_code) : null);
                            }),
                        TextInput::make('description')->hiddenLabel()->placeholder(__('erp.timesheets.description_placeholder'))->maxLength(255),
                    ]),
                Toggle::make('lunch_break')
                    ->label(__('erp.timesheets.lunch_break'))
                    ->helperText(fn (): ?string => $locked() ? __('erp.timesheets.lunch_locked') : __('erp.timesheets.lunch_help', ['hours' => str_replace('.', ',', (string) app(Timesheets::class)->lunchHours())]))
                    ->disabled($locked)
                    ->hidden($fixed)
                    ->live(),
                Textarea::make('remarks')->label(__('erp.timesheets.remarks'))->rows(2)->maxLength(1000),
            ]);
    }

    /**
     * What the system knows about this day: vacation, absence, training or public holiday.
     *
     * @return array{type: string, note: string, credit: float, lunch_locked: bool}|null
     */
    public function status(): ?array
    {
        return app(Timesheets::class)->statuses($this->employeeUser(), $this->day(), $this->day())[$this->day()->toDateString()] ?? null;
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);

        $state = $this->form->getState();
        $user = $this->employeeUser();
        $day = $this->day();
        abort_if($day->isWeekend(), 422);

        app(SaveTimesheetDay::class)->handle($user, $day, $state, auth()->user());

        Notification::make()->title(__('erp.timesheets.saved'))->success()->send();
    }

    /**
     * Projects of the employee's planning this day first.
     *
     * @return array<int, string>
     */
    protected function plannedProjectOptions(): array
    {
        return app(Timesheets::class)->plannedProjects($this->employeeUser(), $this->day())
            ->mapWithKeys(fn (Project $project): array => [$project->id => Timesheets::imputation($project).($project->subtitle() ? ' — '.$project->subtitle() : '')])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function projectSearch(string $search): array
    {
        return app(Timesheets::class)->searchProjects($this->employeeUser(), $search)
            ->mapWithKeys(fn (Project $project): array => [$project->id => Timesheets::imputation($project).' · '.$project->reference.($project->subtitle() ? ' — '.$project->subtitle() : '')])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $timesheets = app(Timesheets::class);
        $week = $timesheets->week($this->employeeUser(), $this->day());
        $rows = Timesheets::normaliseRows((array) ($this->data['rows'] ?? []));
        $lunch = (bool) ($this->data['lunch_break'] ?? true);
        $status = $this->status();
        $fixed = $timesheets->fixedDay($this->employeeUser());
        $worked = $fixed ? $timesheets->fixedWorked($status) : $timesheets->workedHours($rows, $lunch);

        return [
            'employeeName' => $this->employeeUser()->name,
            'week' => $week,
            'status' => $status,
            'rowHours' => array_map(fn (array $row): ?float => Timesheets::hours($row['arrival'], $row['departure']), $rows),
            'rowKm' => array_map(fn (array $row): ?int => Timesheets::rowKm($row), $rows),
            'dayWorked' => round($worked, 2),
            'dayTotal' => round($worked + (float) ($status['credit'] ?? 0), 2),
            'dayKm' => $fixed ? 0 : Timesheets::km($rows),
            'fixedDay' => $fixed,
            'employees' => $this->employeeOptions(),
            'canEdit' => $this->canEdit(),
            'sent' => $week['record']?->isSent(),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('prefill')
                ->label(__('erp.timesheets.prefill'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => $this->canEdit() && modules()->planning())
                ->requiresConfirmation()
                ->modalDescription(__('erp.timesheets.prefill_help'))
                ->action(function (): void {
                    $planned = app(Timesheets::class)->prefillRows($this->employeeUser(), $this->day());
                    $rows = Timesheets::normaliseRows((array) ($this->data['rows'] ?? []));
                    $end = array_pop($rows);
                    array_pop($planned);

                    // Projects go on the site row and the extra rows, by position; extra rows are added when needed.
                    foreach ($planned as $index => $row) {
                        if ($row['project_id']) {
                            $rows[$index] = [...($rows[$index] ?? [...$row, 'arrival' => null, 'departure' => null, 'km_departure' => null, 'km_arrival' => null]),
                                'project_id' => $row['project_id'], 'imputation' => $row['imputation'], 'description' => $row['description']];
                        }
                    }

                    $this->form->fill([...$this->data, 'rows' => [...$rows, $end]]);
                }),
            Action::make('weekSettings')
                ->label(__('erp.timesheets.week_settings'))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()->isAdmin() || auth()->user()->hasPermission('timesheets.edit_all'))
                ->fillForm(fn (): array => ($record = $this->weekRecord()) ? $record->only(['plate_number', 'km_carried', 'overtime_carried', 'expected_hours', 'remarks']) : ['plate_number' => app(Timesheets::class)->plateFor($this->employeeUser())])
                ->schema([
                    TextInput::make('plate_number')->label(__('erp.timesheets.plate'))->maxLength(20),
                    TextInput::make('expected_hours')->label(__('erp.timesheets.expected_hours'))->numeric()->minValue(0)->maxValue(80)->step('any')->placeholder((string) app(Timesheets::class)->expectedHours()),
                    TextInput::make('km_carried')->label(__('erp.timesheets.km_carried'))->helperText(__('erp.timesheets.carried_help'))->integer(),
                    TextInput::make('overtime_carried')->label(__('erp.timesheets.overtime_carried'))->helperText(__('erp.timesheets.carried_help'))->numeric()->step('any'),
                    Textarea::make('remarks')->label(__('erp.timesheets.week_remarks'))->rows(2)->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    TimesheetWeek::query()->updateOrCreate(
                        ['user_id' => $this->employeeUser()->id, 'week_start' => Timesheets::monday($this->day())->toDateString()],
                        $data,
                    );
                }),
            Action::make('pdf')
                ->label(__('erp.timesheets.week_pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'timesheets'))
                ->url(fn (): string => route('timesheets.pdf', ['user' => $this->employeeUser()->id, 'week' => Timesheets::monday($this->day())->toDateString()]), shouldOpenInNewTab: true),
            Action::make('send')
                ->label(__('erp.timesheets.send_week'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => auth()->user()->isAdmin() || auth()->user()->hasPermission('timesheets.send'))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('erp.timesheets.send_week_help', ['recipients' => app(Timesheets::class)->recipientsDescription()]))
                ->action(function (): void {
                    $result = app(SendWeeklyTimesheets::class)->handle(Timesheets::monday($this->day()), [$this->employeeUser()]);

                    $result['sent']
                        ? Notification::make()->title(__('erp.timesheets.sent'))->success()->send()
                        : Notification::make()->title(__('erp.timesheets.not_sent'))->body($result['error'])->danger()->send();
                }),
        ];
    }
}
