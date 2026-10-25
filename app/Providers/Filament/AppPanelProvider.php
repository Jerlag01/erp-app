<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Announcement;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FinanceOverview;
use App\Filament\Pages\FinanceStatistics;
use App\Filament\Pages\HrAdvances;
use App\Filament\Pages\Onboarding;
use App\Filament\Pages\Planning;
use App\Filament\Pages\Timesheet;
use App\Filament\Pages\TimesheetOverview;
use App\Filament\Pages\ToolboxOverview;
use App\Filament\Pages\VacationCalendar;
use App\Filament\Pages\WasteTotalsOverview;
use App\Filament\Widgets\ControlsDue;
use App\Filament\Widgets\DashboardCountersWidget;
use App\Filament\Widgets\ErpStats;
use App\Filament\Widgets\InspectionsDue;
use App\Filament\Widgets\ItOverview;
use App\Filament\Widgets\LowStock;
use App\Filament\Widgets\OpenDamages;
use App\Providers\Filament\Concerns\ConfiguresErpPanel;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;

/**
 * The employee side: every active user, with access to modules based on their role's permissions.
 */
class AppPanelProvider extends PanelProvider
{
    use ConfiguresErpPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureErpPanel($panel, 'app', '', 'ERP')
            ->default()
            // The administrators' message and the idea/complaint box sit in the top bar, left of the search field.
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => view('filament.app.banner'))
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => view('filament.app.suggestion-link'))
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->pages([
                Dashboard::class,
                VacationCalendar::class,
                WasteTotalsOverview::class,
                FinanceOverview::class,
                FinanceStatistics::class,
                Planning::class,
                Timesheet::class,
                TimesheetOverview::class,
                ToolboxOverview::class,
                Announcement::class,
                Onboarding::class,
                HrAdvances::class,
            ])
            ->widgets([
                DashboardCountersWidget::class,
                ErpStats::class,
                ControlsDue::class,
                InspectionsDue::class,
                OpenDamages::class,
                LowStock::class,
                ItOverview::class,
            ]);
    }
}
