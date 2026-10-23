<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\Employee;
use App\Support\HrAdvances as Calculator;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * HR calculations: the monthly advance of workers and servants. Month, period (from – until), the
 * start value of each statute and of each employee can be changed for a calculation; "Save as
 * default" keeps the start values and the last day of the period for the next months.
 * Only with "hr_calculations.view" (not given to administrators automatically).
 */
class HrAdvances extends Page
{
    protected static ?string $slug = 'hr-calculations/advances';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.hr-advances';

    #[Url]
    public string $month = '';

    #[Url]
    public string $group = Calculator::WORKERS;

    public string $from = '';

    public string $until = '';

    /** @var array<string, float|string|null> */
    public array $bases = [];

    /** @var array<int|string, float|string|null> Start value per employee, when it differs. */
    public array $overrides = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && modules()->hrCalculations() && $user->hasPermission('hr_calculations.view');
    }

    public static function canManage(): bool
    {
        return (bool) auth()->user()?->hasPermission('hr_calculations.manage');
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.hr.advances.title');
    }

    public function getTitle(): string
    {
        return __('erp.hr.advances.title');
    }

    public function getSubheading(): ?string
    {
        return __('erp.hr.advances.intro');
    }

    public function mount(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : now()->format('Y-m');
        $this->group = in_array($this->group, [Calculator::WORKERS, Calculator::SERVANTS], true) ? $this->group : Calculator::WORKERS;
        $this->bases = [Calculator::WORKERS => Calculator::defaultBase(Calculator::WORKERS), Calculator::SERVANTS => Calculator::defaultBase(Calculator::SERVANTS)];
        $this->resetPeriod();
    }

    public function updatedMonth(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : now()->format('Y-m');
        $this->resetPeriod();
    }

    public function resetPeriod(): void
    {
        [$from, $until] = Calculator::defaultPeriod(CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth());
        $this->from = $from->toDateString();
        $this->until = $until->toDateString();
    }

    public function setGroup(string $group): void
    {
        $this->group = $group === Calculator::SERVANTS ? Calculator::SERVANTS : Calculator::WORKERS;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function period(): array
    {
        $from = rescue(fn () => CarbonImmutable::parse($this->from)->startOfDay(), null, false) ?? now()->startOfMonth()->toImmutable();
        $until = rescue(fn () => CarbonImmutable::parse($this->until)->startOfDay(), null, false) ?? $from;

        return $until->lessThan($from) ? [$from, $from] : [$from, $until->min($from->addMonths(2))];
    }

    protected function base(string $group): float
    {
        return max(0.0, (float) str_replace(',', '.', (string) ($this->bases[$group] ?? Calculator::defaultBase($group))));
    }

    /**
     * @return list<array{employee: Employee, result: array<string, mixed>, overridden: bool}>
     */
    public function rows(?string $group = null): array
    {
        $group ??= $this->group;
        [$from, $until] = $this->period();
        $calculator = app(Calculator::class);

        return $calculator->employees($group)->map(function (Employee $employee) use ($calculator, $from, $until, $group): array {
            $override = $this->overrides[$employee->id] ?? null;
            $overridden = $override !== null && $override !== '';
            $base = $overridden ? max(0.0, (float) str_replace(',', '.', (string) $override)) : $this->base($group);

            return ['employee' => $employee, 'result' => $calculator->calculate($employee, $from, $until, $base), 'overridden' => $overridden];
        })->values()->all();
    }

    public function total(): float
    {
        return round(array_sum(array_map(fn (array $row): float => $row['result']['advance'], $this->rows())), 2);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label(__('erp.hr.advances.export_pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->pdf()),
            Action::make('saveDefaults')
                ->label(__('erp.hr.advances.save_defaults'))
                ->icon(Heroicon::OutlinedBookmark)
                ->color('gray')
                ->visible(fn (): bool => static::canManage())
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('erp.hr.advances.save_defaults_help', [
                    'worker' => number_format($this->base(Calculator::WORKERS), 2, ',', '.'),
                    'servant' => number_format($this->base(Calculator::SERVANTS), 2, ',', '.'),
                    'day' => $this->period()[1]->day,
                ]))
                ->action(function (): void {
                    abort_unless(static::canManage(), 403);

                    settings()->save('hr', [
                        ...(array) settings()->get('hr'),
                        'advance_worker' => $this->base(Calculator::WORKERS),
                        'advance_servant' => $this->base(Calculator::SERVANTS),
                        'advance_until_day' => $this->period()[1]->day,
                    ]);

                    Notification::make()->title(__('erp.hr.advances.defaults_saved'))->success()->send();
                }),
        ];
    }

    protected function pdf(): StreamedResponse
    {
        [$from, $until] = $this->period();
        $groups = [];

        foreach ([Calculator::WORKERS, Calculator::SERVANTS] as $group) {
            $groups[$group] = $this->rows($group);
        }

        $title = __('erp.hr.advances.pdf_title', ['month' => ucfirst(CarbonImmutable::createFromFormat('Y-m', $this->month)->locale(app()->getLocale())->translatedFormat('F Y'))]);
        $pdf = Pdf::loadView('hr.advances-pdf', ['title' => $title, 'from' => $from, 'until' => $until, 'groups' => $groups])
            ->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();

        return response()->streamDownload(fn () => print ($pdf), Str::slug($title).'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
