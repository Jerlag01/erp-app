<?php

namespace App\Support;

use App\Filament\App\Widgets\MyAssets;
use App\Filament\App\Widgets\MyTeams;
use App\Filament\App\Widgets\MyVehicles;
use App\Filament\App\Widgets\ReportIncidentCard;
use App\Filament\App\Widgets\VacationBalance;
use App\Filament\Widgets\ControlsDue;
use App\Filament\Widgets\DashboardCountersWidget;
use App\Filament\Widgets\ErpStats;
use App\Filament\Widgets\InspectionsDue;
use App\Filament\Widgets\ItOverview;
use App\Filament\Widgets\LowStock;
use App\Filament\Widgets\OpenDamages;
use App\Models\User;

/**
 * Everyone arranges their own dashboard per panel: the number of columns, the blocks ("modules") in their
 * order and the counters. Only blocks and counters the user may see can be chosen; without a layout of
 * their own the standard dashboard is shown.
 */
class DashboardLayout
{
    public const DEFAULT_COUNTERS = ['my_planning_today', 'open_projects', 'projects_to_invoice', 'vacation_requests_pending', 'employee_requests'];

    /**
     * Block key => widget class, per panel (in the standard order).
     *
     * @return array<string, class-string>
     */
    public static function widgets(string $panel): array
    {
        $shared = [
            'counters' => DashboardCountersWidget::class,
            'stats' => ErpStats::class,
            'controls_due' => ControlsDue::class,
            'inspections_due' => InspectionsDue::class,
            'open_damages' => OpenDamages::class,
            'low_stock' => LowStock::class,
            'it_overview' => ItOverview::class,
        ];

        if ($panel !== 'app') {
            return $shared;
        }

        return [
            'counters' => DashboardCountersWidget::class,
            'report_incident' => ReportIncidentCard::class,
            'vacation_balance' => VacationBalance::class,
            ...array_slice($shared, 1, null, true),
            'my_teams' => MyTeams::class,
            'my_vehicles' => MyVehicles::class,
            'my_assets' => MyAssets::class,
        ];
    }

    /**
     * The blocks this user may put on the dashboard: key => label.
     *
     * @return array<string, string>
     */
    public static function availableWidgets(string $panel): array
    {
        return collect(self::widgets($panel))
            ->filter(fn (string $class): bool => (bool) rescue(fn (): bool => $class::canView(), false, false))
            ->mapWithKeys(fn (string $class, string $key): array => [$key => __('erp.dashboard.widgets.'.$key)])
            ->all();
    }

    /**
     * @return array{columns: int, widgets: list<string>, counters: list<string>, custom: bool}
     */
    public static function forUser(User $user, string $panel): array
    {
        $own = (array) (($user->dashboard ?? [])[$panel] ?? []);

        if ($own === []) {
            return [
                'columns' => 2,
                'widgets' => array_keys(self::widgets($panel)),
                'counters' => self::DEFAULT_COUNTERS,
                'custom' => false,
            ];
        }

        return [
            'columns' => max(1, min(3, (int) ($own['columns'] ?? 2))),
            'widgets' => array_values(array_intersect((array) ($own['widgets'] ?? []), array_keys(self::widgets($panel)))),
            'counters' => array_values(array_intersect((array) ($own['counters'] ?? []), array_keys(DashboardCounters::all()))),
            'custom' => true,
        ];
    }

    /**
     * @param  array{columns?: mixed, widgets?: mixed, counters?: mixed}  $layout
     */
    public static function save(User $user, string $panel, array $layout): void
    {
        $dashboard = (array) ($user->dashboard ?? []);
        $dashboard[$panel] = [
            'columns' => max(1, min(3, (int) ($layout['columns'] ?? 2))),
            'widgets' => array_values(array_unique(array_intersect(array_values((array) ($layout['widgets'] ?? [])), array_keys(self::availableWidgets($panel))))),
            'counters' => array_values(array_unique(array_intersect(array_values((array) ($layout['counters'] ?? [])), array_keys(DashboardCounters::available($user))))),
        ];

        $user->forceFill(['dashboard' => $dashboard])->save();
    }

    public static function reset(User $user, string $panel): void
    {
        $dashboard = (array) ($user->dashboard ?? []);
        unset($dashboard[$panel]);

        $user->forceFill(['dashboard' => $dashboard ?: null])->save();
    }
}
