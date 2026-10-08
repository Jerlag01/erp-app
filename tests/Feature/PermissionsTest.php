<?php

namespace Tests\Feature;

use App\Filament\App\Widgets\MyAssets;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Assets\RelationManagers\LogsRelationManager;
use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_employee_side_follows_the_role_permissions(): void
    {
        $team = Team::factory()->create();
        $vehicle = Vehicle::factory()->for($team)->create();
        $viewer = User::factory()->withPermissions(['vehicles' => ['view']])->inTeam($team)->create();

        $this->actingAs($viewer)->get('/vehicles')->assertOk()->assertSee($vehicle->plate_number);
        $this->actingAs($viewer)->get("/vehicles/{$vehicle->id}")->assertOk();
        $this->actingAs($viewer)->get('/vehicles/create')->assertForbidden();
        $this->actingAs($viewer)->get("/vehicles/{$vehicle->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->get('/assets')->assertForbidden();
        $this->actingAs($viewer)->get('/teams')->assertForbidden();
    }

    #[Test]
    public function roles_only_store_known_permissions(): void
    {
        $role = Role::create([
            'name' => 'Test',
            'permissions' => ['vehicles' => ['view', 'fly'], 'secrets' => ['view'], 'assets' => []],
        ]);

        $this->assertSame(['vehicles' => ['view']], $role->fresh()->permissions);
    }

    #[Test]
    public function an_employee_sees_the_equipment_of_their_team_and_vehicle_and_can_report_damage(): void
    {
        $employee = User::factory()->withPermissions(['asset_logs' => ['create']])->create();
        $team = Team::factory()->create();
        $team->members()->attach($employee);
        $vehicle = Vehicle::factory()->create(['driver_id' => $employee->id]);

        $viaTeam = Asset::factory()->for($team)->create();
        $viaVehicle = Asset::factory()->for($vehicle)->create();
        $personal = Asset::factory()->create(['user_id' => $employee->id]);
        $someoneElses = Asset::factory()->create();

        $this->actingAs($employee);
        Filament::setCurrentPanel('app');

        Livewire::test(MyAssets::class)
            ->assertCanSeeTableRecords([$viaTeam, $viaVehicle, $personal])
            ->assertCanNotSeeTableRecords([$someoneElses])
            ->callTableAction('reportDamage', $personal, ['title' => 'Kabel beschadigd', 'severity' => 'major'])
            ->assertHasNoTableActionErrors();

        $damage = AssetLog::sole();
        $this->assertSame($personal->id, $damage->asset_id);
        $this->assertSame($employee->id, $damage->user_id);
        $this->assertTrue($damage->isOpenDamage());
    }

    #[Test]
    public function without_teams_the_team_equipment_is_not_shown_as_the_employees_own(): void
    {
        $employee = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($employee);
        $viaTeam = Asset::factory()->for($team)->create();

        $this->setModules(['teams' => false]);

        $this->assertFalse(Asset::usedBy($employee)->whereKey($viaTeam->id)->exists());
    }

    #[Test]
    public function the_asset_log_on_the_view_page_respects_log_permissions(): void
    {
        $team = Team::factory()->create();
        $asset = Asset::factory()->for($team)->create();
        $reader = User::factory()->withPermissions(['assets' => ['view'], 'asset_logs' => ['view']])->inTeam($team)->create();
        $reporter = User::factory()->withPermissions(['assets' => ['view'], 'asset_logs' => ['view', 'create']])->inTeam($team)->create();
        Filament::setCurrentPanel('app');

        Livewire::actingAs($reader)
            ->test(LogsRelationManager::class, ['ownerRecord' => $asset, 'pageClass' => ViewAsset::class])
            ->assertTableActionHidden('create');

        Livewire::actingAs($reporter)
            ->test(LogsRelationManager::class, ['ownerRecord' => $asset, 'pageClass' => ViewAsset::class])
            ->assertTableActionVisible('create');
    }
}
