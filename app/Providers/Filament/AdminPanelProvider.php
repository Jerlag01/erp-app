<?php

namespace App\Providers\Filament;

use App\Filament\Pages\FinanceOverview;
use App\Filament\Pages\FinanceStatistics;
use App\Filament\Pages\Planning;
use App\Filament\Pages\Timesheet;
use App\Filament\Pages\TimesheetOverview;
use App\Filament\Pages\VacationCalendar;
use App\Filament\Pages\WasteTotalsOverview;
use App\Filament\Widgets\ControlsDue;
use App\Filament\Widgets\ErpStats;
use App\Filament\Widgets\InspectionsDue;
use App\Filament\Widgets\ItOverview;
use App\Filament\Widgets\LowStock;
use App\Filament\Widgets\OpenDamages;
use App\Providers\Filament\Concerns\ConfiguresErpPanel;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * The admin side: administrators manage everything, including users, roles and settings.
 */
class AdminPanelProvider extends PanelProvider
{
    use ConfiguresErpPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureErpPanel($panel, 'admin', 'admin', 'Admin')
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->pages([
                Dashboard::class,
                VacationCalendar::class,
                WasteTotalsOverview::class,
                FinanceOverview::class,
                FinanceStatistics::class,
                Planning::class,
                Timesheet::class,
                TimesheetOverview::class,
            ])
            ->widgets([
                ErpStats::class,
                ControlsDue::class,
                InspectionsDue::class,
                OpenDamages::class,
                LowStock::class,
                ItOverview::class,
            ]);
    }
}
