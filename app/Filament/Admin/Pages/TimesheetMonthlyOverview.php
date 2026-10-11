<?php

namespace App\Filament\Admin\Pages;

use App\Enums\NavigationGroup;
use App\Support\TimesheetReport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Everyone's prestation hours and kilometres per month, like the Excel "prestatiefiche power", with
 * PDFs of a month, two months or a year.
 */
class TimesheetMonthlyOverview extends Page
{
    protected string $view = 'filament.admin.pages.timesheet-monthly-overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::HumanResources;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'timesheet-report';

    /** Y-m */
    #[Url]
    public ?string $month = null;

    /** 1, 2 or year */
    #[Url]
    public string $span = '1';

    public static function canAccess(): bool
    {
        return modules()->timesheets() && (bool) auth()->user()?->isAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.timesheets.report');
    }

    public function getTitle(): string
    {
        return __('erp.timesheets.report');
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $this->month) ? $this->month : today()->format('Y-m');
        $this->span = in_array($this->span, ['1', '2', 'year'], true) ? $this->span : '1';
    }

    public function current(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', (string) $this->month);
    }

    public function previous(): void
    {
        $this->month = ($this->span === 'year' ? $this->current()->subYear() : $this->current()->subMonthsNoOverflow((int) $this->span))->format('Y-m');
    }

    public function next(): void
    {
        $this->month = ($this->span === 'year' ? $this->current()->addYear() : $this->current()->addMonthsNoOverflow((int) $this->span))->format('Y-m');
    }

    public function setSpan(string $span): void
    {
        $this->span = in_array($span, ['1', '2', 'year'], true) ? $span : '1';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $months = TimesheetReport::months($this->current(), $this->span);

        return [
            'report' => app(TimesheetReport::class)->build($months),
            'title' => self::title($months),
            'rate' => app(TimesheetReport::class)->mobilityRate(),
        ];
    }

    /**
     * "Prestaties oktober 2026", "Prestaties oktober – november 2026", "Prestaties 2026".
     *
     * @param  list<CarbonImmutable>  $months
     */
    public static function title(array $months): string
    {
        $name = fn (CarbonImmutable $month): string => $month->locale('nl')->translatedFormat('F');

        return match (count($months)) {
            1 => 'Prestaties '.$name($months[0]).' '.$months[0]->year,
            12 => 'Prestaties '.$months[0]->year,
            default => 'Prestaties '.$name($months[0]).($months[0]->year !== end($months)->year ? ' '.$months[0]->year : '').' – '.$name(end($months)).' '.end($months)->year,
        };
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        $pdf = fn (string $span, string $label): Action => Action::make('pdf_'.$span)
            ->label($label)
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->url(fn (): string => route('timesheets.report-pdf', ['month' => $this->month, 'span' => $span]), shouldOpenInNewTab: true);

        return [
            ActionGroup::make([
                $pdf('1', __('erp.timesheets.report_pdf_month')),
                $pdf('2', __('erp.timesheets.report_pdf_two_months')),
                $pdf('year', __('erp.timesheets.report_pdf_year')),
            ])
                ->label('PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->button(),
        ];
    }
}
