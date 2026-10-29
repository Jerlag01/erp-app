<?php

namespace App\Support;

use App\Filament\Pages\HrAdvances;
use App\Filament\Pages\Planning as PlanningPage;
use App\Filament\Pages\Timesheet;
use App\Filament\Resources\GpwRequests\GpwRequestResource;
use App\Filament\Resources\Interventions\InterventionResource;
use App\Models\Asbuilt;
use App\Models\Asset;
use App\Models\BatteryDossier;
use App\Models\BatteryRoomDossier;
use App\Models\CableCalculation;
use App\Models\CableStrippingLine;
use App\Models\ChargingStation;
use App\Models\EnersysDossier;
use App\Models\FuelPass;
use App\Models\Incident;
use App\Models\Location;
use App\Models\MoveDossier;
use App\Models\OrderReference;
use App\Models\Project;
use App\Models\Router;
use App\Models\Suggestion;
use App\Models\Team;
use App\Models\ToolboxMeeting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkSite;
use Illuminate\Support\Facades\Gate;

/**
 * Who reads a module's article of the ERP manual: those who can open that module (its menu item),
 * by its permissions or as an employee using it (own planning, prestations, leave, reporting an
 * incident …). Unknown keys are readable by everyone. Some modules are checked through their page,
 * which looks at the signed-in user: the reader of the knowledge base.
 */
class ManualAccess
{
    public static function allows(User $user, ?string $module): bool
    {
        if ($module === null) {
            return true;
        }

        $gate = Gate::forUser($user);
        $can = fn (string $ability, string ...$models): bool => collect($models)->contains(fn (string $model): bool => $gate->allows($ability, $model));
        // Any ability of these permission areas, while their module is on.
        $any = fn (string ...$areas): bool => collect($areas)->contains(fn (string $area): bool => (($module = Permissions::moduleOf($area)) === null || modules()->enabled($module))
            && collect(Permissions::all()[$area] ?? [])->contains(fn (string $ability): bool => $user->hasPermission("{$area}.{$ability}")));

        return match ($module) {
            'getting-started', 'mobile-app' => true,
            'knowledge-base' => modules()->knowledgeBase(),
            'announcements' => modules()->enabled(Modules::ANNOUNCEMENTS),
            'onboarding' => modules()->onboarding(),
            'roles-users', 'settings', 'client-portal' => $user->isAdmin(),
            'employees' => $user->isAdmin() || $any('access_control'),
            'locations' => $can('viewAny', Location::class),
            'teams' => modules()->teams() && ($can('viewAny', Team::class) || $user->teamIds() !== []),
            'leave' => modules()->vacations() && ($user->hasTimesheet() || $user->hasPermission('vacations.approve')),
            'timesheets' => Timesheet::canAccess(),
            'hr-calculations' => HrAdvances::canAccess(),
            'planning' => PlanningPage::canAccess(),
            'fleet' => $can('viewAny', Vehicle::class, FuelPass::class),
            'assets' => $can('viewAny', Asset::class),
            'orders' => $can('viewAny', OrderReference::class),
            'stock' => $any('stock_items', 'stock', 'stock_prices', 'stock_master_data'),
            'it' => $any('it_assets', 'it_licenses', 'it_items', 'it_master_data'),
            'work-sites' => $can('viewAny', WorkSite::class) || $any('work_site_master_data'),
            'projects' => $can('viewAny', Project::class),
            'gpw' => GpwRequestResource::canViewAny(),
            'interventions' => InterventionResource::canViewAny(),
            'batteries' => $can('viewAny', BatteryDossier::class, BatteryRoomDossier::class) || $any('battery_master_data'),
            'enersys' => $can('viewAny', EnersysDossier::class, MoveDossier::class) || $any('board_deliveries'),
            'charging' => $can('viewAny', ChargingStation::class, Router::class),
            'asbuilt' => $can('viewAny', Asbuilt::class) || ($user->hasPermission('projects.view') && modules()->asbuilt()),
            'cable-calculator' => $can('viewAny', CableCalculation::class),
            'cable-stripping' => $can('viewAny', CableStrippingLine::class),
            'waste' => $any('waste', 'waste_master_data'),
            'finance' => $any('finance', 'clients', 'invoices', 'credit_notes', 'expenses', 'finance_master_data'),
            'incidents' => $can('create', Incident::class) || $can('viewAny', Incident::class),
            'suggestions' => $can('create', Suggestion::class) || $can('viewAny', Suggestion::class),
            'toolbox' => $can('viewAny', ToolboxMeeting::class),
            default => true,
        };
    }
}
