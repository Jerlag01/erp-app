<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\DashboardCounters;
use App\Support\DashboardLayout;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The counters the user chose for their dashboard ("Dashboard aanpassen"), each linking to its list.
 */
class DashboardCountersWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public string $panel = 'app';

    public static function canView(): bool
    {
        return auth()->user() instanceof User;
    }

    protected function getStats(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $all = DashboardCounters::all();
        $allowed = DashboardCounters::available($user);

        return collect(DashboardLayout::forUser($user, $this->panel)['counters'])
            ->filter(fn (string $key): bool => array_key_exists($key, $allowed))
            ->map(function (string $key) use ($all, $allowed, $user): Stat {
                $counter = $all[$key];
                $count = (int) rescue(fn (): int => ($counter['count'])($user), 0, false);

                return Stat::make($allowed[$key], $count)
                    ->icon($counter['icon'])
                    ->color($count > 0 ? $counter['color'] : 'gray')
                    ->url(rescue(fn (): ?string => ($counter['url'])(), null, false));
            })
            ->values()
            ->all();
    }
}
