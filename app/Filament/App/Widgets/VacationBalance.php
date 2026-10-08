<?php

namespace App\Filament\App\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

/**
 * The signed-in employee's vacation days for the current year.
 */
class VacationBalance extends StatsOverviewWidget
{
    protected static ?int $sort = -4;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return modules()->vacations() && auth()->check();
    }

    #[On('vacation-balance-changed')]
    public function refreshBalance(): void {}

    protected function getHeading(): ?string
    {
        return __('erp.vacations.balance_heading', ['year' => date('Y')]);
    }

    protected function getStats(): array
    {
        $balance = auth()->user()->vacationBalance();
        $format = fn (float $days): string => rtrim(rtrim(number_format($days, 1, ',', '.'), '0'), ',');

        return [
            Stat::make(__('erp.vacations.allowance'), $format($balance['allowance']))
                ->icon(Heroicon::OutlinedCalendarDays),
            Stat::make(__('erp.vacations.taken'), $format($balance['approved']))
                ->icon(Heroicon::OutlinedCheckCircle),
            Stat::make(__('erp.vacations.pending'), $format($balance['pending']))
                ->icon(Heroicon::OutlinedClock),
            Stat::make(__('erp.vacations.remaining'), $format($balance['remaining']))
                ->color($balance['remaining'] > 0 ? 'success' : 'danger')
                ->icon(Heroicon::OutlinedSun),
        ];
    }
}
