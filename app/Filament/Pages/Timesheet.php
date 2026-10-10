<?php

namespace App\Filament\Pages;

use App\Actions\Timesheets\SendWeeklyTimesheets;
use App\Enums\NavigationGroup;
use App\Models\Project;
use App\Models\TimesheetDay;
use App\Models\TimesheetWeek;
use App\Models\User;
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
use Illuminate\Database\Eloquent\Builder;
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
        return modules()->timesheets() && auth()->check();
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

        return User::query()->active()->orderBy('name')->pluck('name', 'id')->all();
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

        return $schema
            ->statePath('data')
            ->disabled(fn (): bool => ! $this->canEdit())
            ->components([
                Repeater::make('rows')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->table([
                        Repeater\TableColumn::make(''),
                        Repeater\TableColumn::make(__('erp.timesheets.km_departure')),
                        Repeater\TableColumn::make(__('erp.timesheets.km_arrival')),
                        Repeater\TableColumn::make(__('erp.timesheets.arrival')),
                        Repeater\TableColumn::make(__('erp.timesheets.departure')),
                        Repeater\TableColumn::make(__('erp.timesheets.imputation')),
                        Repeater\TableColumn::make(__('erp.timesheets.description')),
                    ])
                    ->schema([
                        Text::make(fn (Get $get): string => __('erp.timesheets.kinds.'.$get('kind'))),
                        TextInput::make('km_departure')->hiddenLabel()->integer()->minValue(0)->maxValue(9_999_999)->live(onBlur: true),
                        TextInput::make('km_arrival')->hiddenLabel()->integer()->minValue(0)->maxValue(9_999_999)->live(onBlur: true)
                            ->rule(fn (Get $get) => filled($get('km_departure')) ? 'gte:'.(int) $get('km_departure') : null),
                        TextInput::make('arrival')->hiddenLabel()->type('time')->live(onBlur: true),
                        TextInput::make('departure')->hiddenLabel()->type('time')->live(onBlur: true)
                            ->rule(fn (Get $get) => filled($get('arrival')) ? 'after_or_equal:'.$get('arrival') : null),
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

        $rows = Timesheets::normaliseRows((array) ($state['rows'] ?? []));

        // Imputation and description follow the chosen project.
        foreach ($rows as $index => $row) {
            if ($row['project_id'] && ($project = Project::query()->with(['category', 'workSite'])->find($row['project_id']))) {
                $rows[$index]['imputation'] = Timesheets::imputation($project);
                $rows[$index]['description'] ??= $project->workSite?->cow_code ?? $project->cow_code;
            } else {
                $rows[$index]['project_id'] = null;
            }
        }

        TimesheetDay::query()->updateOrCreate(['user_id' => $user->id, 'date' => $day->toDateString()], [
            'rows' => $rows,
            'lunch_break' => ($this->status()['lunch_locked'] ?? false) || (bool) ($state['lunch_break'] ?? true),
            'remarks' => $state['remarks'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        TimesheetWeek::query()->firstOrCreate(
            ['user_id' => $user->id, 'week_start' => Timesheets::monday($day)->toDateString()],
            ['plate_number' => app(Timesheets::class)->plateFor($user)],
        );

        Notification::make()->title(__('erp.timesheets.saved'))->success()->send();
    }

    /**
     * Projects of the employee's planning this day first.
     *
     * @return array<int, string>
     */
    protected function plannedProjectOptions(): array
    {
        if (! modules()->planning()) {
            return [];
        }

        return Project::query()
            ->whereHas('planningItems', fn (Builder $query) => $query->where('user_id', $this->employeeUser()->id)->whereDate('date', $this->day()->toDateString()))
            ->with('category')
            ->get()
            ->mapWithKeys(fn (Project $project): array => [$project->id => Timesheets::imputation($project).($project->subtitle() ? ' — '.$project->subtitle() : '')])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function projectSearch(string $search): array
    {
        $term = '%'.addcslashes(trim($search), '%_\\').'%';

        return Project::query()
            ->visibleTo($this->employeeUser())
            ->where(fn (Builder $query) => $query->where('reference', 'like', $term)->orWhere('short_description', 'like', $term)->orWhere('cow_code', 'like', $term)->orWhere('number', 'like', $term))
            ->with('category')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
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
        $worked = array_sum(array_map(fn (int $index): float => Timesheets::hours($rows[$index]['arrival'], $rows[$index]['departure']) ?? 0.0, Timesheets::COUNTED));
        $worked = $worked > 0 && $lunch ? max(0, $worked - $timesheets->lunchHours()) : $worked;

        return [
            'employeeName' => $this->employeeUser()->name,
            'week' => $week,
            'status' => $status,
            'rowHours' => array_map(fn (array $row): ?float => Timesheets::hours($row['arrival'], $row['departure']), $rows),
            'rowKm' => array_map(fn (array $row): ?int => Timesheets::rowKm($row), $rows),
            'dayWorked' => round($worked, 2),
            'dayTotal' => round($worked + (float) ($status['credit'] ?? 0), 2),
            'dayKm' => array_sum(array_map(fn (int $index): int => Timesheets::rowKm($rows[$index]) ?? 0, Timesheets::COUNTED)),
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
                    $rows = array_values((array) ($this->data['rows'] ?? []));

                    foreach ($planned as $index => $row) {
                        if ($row['project_id']) {
                            $rows[$index] = [...($rows[$index] ?? []), 'project_id' => $row['project_id'], 'imputation' => $row['imputation'], 'description' => $row['description']];
                        }
                    }

                    $this->form->fill([...$this->data, 'rows' => $rows]);
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
                ->url(fn (): string => route('timesheets.pdf', ['user' => $this->employeeUser()->id, 'week' => Timesheets::monday($this->day())->toDateString()]), shouldOpenInNewTab: true),
            Action::make('send')
                ->label(__('erp.timesheets.send_week'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => auth()->user()->isAdmin() || auth()->user()->hasPermission('timesheets.send'))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('erp.timesheets.send_week_help', ['recipients' => implode(', ', app(Timesheets::class)->recipients()) ?: '—']))
                ->action(function (): void {
                    $result = app(SendWeeklyTimesheets::class)->handle(Timesheets::monday($this->day()), [$this->employeeUser()]);

                    $result['sent']
                        ? Notification::make()->title(__('erp.timesheets.sent'))->success()->send()
                        : Notification::make()->title(__('erp.timesheets.not_sent'))->body($result['error'])->danger()->send();
                }),
        ];
    }
}
