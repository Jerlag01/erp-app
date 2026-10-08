<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Support\FinanceFigures;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Income, expenses and result over a chosen period (this year by default), with or without VAT,
 * compared with the same period a year earlier, and a monthly chart of invoices, credit notes
 * and incoming invoices.
 */
class FinanceStatistics extends Page
{
    protected static ?string $slug = 'finance-statistics';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 2;

    protected string $view = 'finance.statistics';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    #[Url]
    public bool $vat = false;

    #[Url]
    public bool $compare = true;

    public static function canAccess(): bool
    {
        return FinanceOverview::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('erp.finance.statistics.title');
    }

    public function getTitle(): string
    {
        return __('erp.finance.statistics.title');
    }

    public function mount(): void
    {
        if (! $this->validDate($this->from) || ! $this->validDate($this->until) || $this->from > $this->until) {
            $this->preset('year');
        }
    }

    public function preset(string $preset): void
    {
        $today = CarbonImmutable::today();

        [$from, $until] = match ($preset) {
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()],
            'quarter' => [$today->startOfQuarter(), $today->endOfQuarter()],
            'last_12' => [$today->subMonths(11)->startOfMonth(), $today->endOfMonth()],
            default => [$today->startOfYear(), $today->endOfYear()],
        };

        $this->from = $from->toDateString();
        $this->until = $until->toDateString();
    }

    protected function validDate(?string $date): bool
    {
        return $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && strtotime($date) !== false;
    }

    /**
     * @return array{current: array<string, mixed>, previous: ?array<string, mixed>, totals: array<string, float>, previousTotals: ?array<string, float>}
     */
    public function figures(): array
    {
        $from = $this->validDate($this->from) ? $this->from : now()->startOfYear()->toDateString();
        $until = $this->validDate($this->until) && $this->until >= $from ? $this->until : now()->endOfYear()->toDateString();

        // The months of a period spanning many years are capped, to keep the chart readable.
        if (CarbonImmutable::parse($from)->diffInMonths(CarbonImmutable::parse($until)) > 36) {
            $from = CarbonImmutable::parse($until)->subMonths(35)->startOfMonth()->toDateString();
        }

        $current = FinanceFigures::monthly($from, $until, $this->vat);
        $previous = $this->compare
            ? FinanceFigures::monthly(CarbonImmutable::parse($from)->subYear()->toDateString(), CarbonImmutable::parse($until)->subYear()->toDateString(), $this->vat)
            : null;

        return [
            'current' => $current,
            'previous' => $previous,
            'totals' => FinanceFigures::totals($current),
            'previousTotals' => $previous !== null ? FinanceFigures::totals($previous) : null,
        ];
    }
}
