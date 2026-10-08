<?php

namespace App\Filament\Widgets;

use App\Enums\AssetLogType;
use App\Enums\VehicleStatus;
use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Key figures per module; each figure only shows when its module is on and the user may see it.
 */
class ErpStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin()
            || static::may('teams', 'teams')
            || static::may('fleet', 'vehicles')
            || static::may('assets', 'assets');
    }

    protected static function may(string $module, string $area): bool
    {
        return modules()->enabled($module) && (bool) auth()->user()?->hasPermission("{$area}.view");
    }

    protected function getStats(): array
    {
        $stats = [];
        $user = auth()->user();

        if (auth()->user()?->isAdmin()) {
            $stats[] = Stat::make(__('erp.resources.user.plural'), User::active()->count())
                ->description(__('erp.stats.inactive', ['count' => User::where('is_active', false)->count()]))
                ->icon(Heroicon::OutlinedUsers);
        }

        if (static::may('teams', 'teams')) {
            $stats[] = Stat::make(__('erp.resources.team.plural'), Team::visibleTo($user)->where('is_active', true)->count())
                ->description(__('erp.stats.members', ['count' => User::active()->whereHas('teams', fn ($query) => $query->visibleTo($user))->count()]))
                ->icon(Heroicon::OutlinedUserGroup);
        }

        if (static::may('fleet', 'vehicles')) {
            $due = Vehicle::visibleTo($user)->controlDue()->count();

            $stats[] = Stat::make(__('erp.resources.vehicle.plural'), Vehicle::visibleTo($user)->where('status', '!=', VehicleStatus::Sold)->count())
                ->description(__('erp.stats.controls_due', ['count' => $due]))
                ->descriptionColor($due > 0 ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedTruck);
        }

        if (static::may('assets', 'assets')) {
            $due = Asset::visibleTo($user)->inspectionDue()->count();
            $damaged = AssetLog::visibleTo($user)->where('type', AssetLogType::Damage)->whereNull('resolved_at')->count();

            $stats[] = Stat::make(__('erp.resources.asset.plural'), Asset::visibleTo($user)->count())
                ->description(__('erp.stats.inspections_due', ['count' => $due]))
                ->descriptionColor($due > 0 ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedBolt);
            $stats[] = Stat::make(__('erp.logs.open_damages'), $damaged)
                ->descriptionColor($damaged > 0 ? 'danger' : 'gray')
                ->description(__('erp.stats.damaged_assets', ['count' => Asset::visibleTo($user)->has('openDamages')->count()]))
                ->icon(Heroicon::OutlinedExclamationTriangle);
        }

        return $stats;
    }
}
