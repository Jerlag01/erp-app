<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\TimesheetMonthlyOverview;
use App\Models\Asset;
use App\Models\BatteryDelivery;
use App\Models\Project;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Permissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllTeamsPermissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_team_limited_area_has_the_all_teams_permission(): void
    {
        foreach (['teams', 'vehicles', 'fuel_passes', 'assets', 'asset_logs', 'vacations', 'locations', 'stock_items', 'stock', 'it_assets', 'it_licenses', 'it_items', 'projects', 'batteries', 'enersys', 'battery_rooms', 'board_deliveries'] as $area) {
            $this->assertContains(Permissions::ALL_TEAMS, Permissions::AREAS[$area]['abilities'], $area);
        }
    }

    #[Test]
    public function all_teams_lifts_the_team_limitation_of_that_area_only(): void
    {
        $bureau = Team::factory()->create(['name' => 'Bureau']);
        $field = Team::factory()->create();
        $project = Project::factory()->create();
        $project->teams()->attach($field);
        $vehicle = Vehicle::factory()->create(['team_id' => $field->id]);

        $limited = User::factory()->withPermissions(['projects' => ['view'], 'vehicles' => ['view']])->inTeam($bureau)->create();
        $this->assertFalse(Project::query()->visibleTo($limited)->whereKey($project->id)->exists());
        $this->assertFalse(Vehicle::query()->visibleTo($limited)->whereKey($vehicle->id)->exists());

        $everything = User::factory()->withPermissions(['projects' => ['view', 'all_teams'], 'vehicles' => ['view']])->inTeam($bureau)->create();
        $this->assertTrue(Project::query()->visibleTo($everything)->whereKey($project->id)->exists());
        $this->assertTrue($project->isVisibleTo($everything));
        // Another area keeps its limitation.
        $this->assertFalse(Vehicle::query()->visibleTo($everything)->whereKey($vehicle->id)->exists());

        // Records that follow the project: their own area's all_teams counts as well.
        $delivery = BatteryDelivery::factory()->create(['project_id' => $project->id]);
        $this->assertTrue(BatteryDelivery::query()->visibleTo($everything)->whereKey($delivery->id)->exists());
        $batteries = User::factory()->withPermissions(['batteries' => ['view', 'all_teams']])->inTeam($bureau)->create();
        $this->assertTrue(BatteryDelivery::query()->visibleTo($batteries)->whereKey($delivery->id)->exists());
        $this->assertFalse(BatteryDelivery::query()->visibleTo($limited)->whereKey($delivery->id)->exists());

        // Assets: their own area.
        $asset = Asset::factory()->create(['team_id' => $field->id]);
        $assets = User::factory()->withPermissions(['assets' => ['view', 'all_teams']])->inTeam($bureau)->create();
        $this->assertTrue(Asset::query()->visibleTo($assets)->whereKey($asset->id)->exists());
        $this->assertFalse(Asset::query()->visibleTo($limited)->whereKey($asset->id)->exists());
    }

    #[Test]
    public function the_administration_has_the_monthly_prestations_on_the_employee_side(): void
    {
        $administration = User::factory()->create(['role_id' => Role::query()->where('name', 'Administratie')->sole()->id]);
        $this->actingAs($administration);
        Filament::setCurrentPanel('app');

        $this->assertTrue(TimesheetMonthlyOverview::canAccess());
        $this->get(TimesheetMonthlyOverview::getUrl(panel: 'app'))->assertOk()->assertSee(__('erp.timesheets.report'));

        $this->actingAs(User::factory()->withPermissions([])->inTeam()->create());
        $this->assertFalse(TimesheetMonthlyOverview::canAccess());
        $this->get(TimesheetMonthlyOverview::getUrl(panel: 'app'))->assertForbidden();
    }
}
