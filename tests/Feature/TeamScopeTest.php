<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function employees_only_see_what_is_linked_to_their_own_team(): void
    {
        $mine = Team::factory()->create(['name' => 'Ploeg Luik']);
        $other = Team::factory()->create(['name' => 'Ploeg Namen']);
        $all = ['teams' => ['view'], 'vehicles' => ['view'], 'assets' => ['view'], 'asset_logs' => ['view']];
        $employee = User::factory()->withPermissions($all)->inTeam($mine)->create();

        $myVehicle = Vehicle::factory()->for($mine)->create(['plate_number' => '1-AAA-111']);
        $otherVehicle = Vehicle::factory()->for($other)->create(['plate_number' => '1-ZZZ-999']);
        $myAsset = Asset::factory()->for($mine)->create(['asset_tag' => 'EQ-1111']);
        $assetInMyVan = Asset::factory()->for($myVehicle)->create(['asset_tag' => 'EQ-2222']);
        $otherAsset = Asset::factory()->for($other)->create(['asset_tag' => 'EQ-9999']);
        $otherLog = AssetLog::factory()->for($otherAsset)->damage()->create(['title' => 'Andere ploeg schade']);

        $this->actingAs($employee)->get('/teams')->assertOk()->assertSee('Ploeg Luik')->assertDontSee('Ploeg Namen');
        $this->actingAs($employee)->get('/vehicles')->assertOk()->assertSee('1-AAA-111')->assertDontSee('1-ZZZ-999');
        $this->actingAs($employee)->get('/assets')->assertOk()->assertSee('EQ-1111')->assertSee('EQ-2222')->assertDontSee('EQ-9999');
        $this->actingAs($employee)->get('/asset-logs')->assertOk()->assertDontSee('Andere ploeg schade');

        $this->actingAs($employee)->get("/teams/{$other->id}")->assertNotFound();
        $this->actingAs($employee)->get("/vehicles/{$otherVehicle->id}")->assertNotFound();
        $this->actingAs($employee)->get("/assets/{$otherAsset->id}")->assertNotFound();
        $this->actingAs($employee)->get("/assets/{$myAsset->id}")->assertOk();
    }

    #[Test]
    public function administrators_see_every_team(): void
    {
        $admin = User::factory()->admin()->create();
        Vehicle::factory()->for(Team::factory())->create(['plate_number' => '1-AAA-111']);
        Vehicle::factory()->for(Team::factory())->create(['plate_number' => '1-ZZZ-999']);

        $this->actingAs($admin)->get('/vehicles')->assertSee('1-AAA-111')->assertSee('1-ZZZ-999');
    }

    #[Test]
    public function employees_without_a_team_are_guests_without_permissions(): void
    {
        $employee = User::factory()->withPermissions(['vehicles' => ['view']])->create();

        $this->assertTrue($employee->isGuest());
        $this->assertFalse($employee->hasPermission('vehicles.view'));
        $this->assertSame('Gast / Magazijn', $employee->effectiveRole()->name);
        $this->actingAs($employee)->get('/')->assertOk();
        $this->actingAs($employee)->get('/vehicles')->assertForbidden();

        $employee->teams()->attach(Team::factory()->create());
        $employee->flushTeamIds();

        $this->assertFalse($employee->isGuest());
        $this->assertTrue($employee->hasPermission('vehicles.view'));
    }

    #[Test]
    public function the_guest_role_never_gets_permissions_and_cannot_be_deleted(): void
    {
        $guest = Role::guest();
        $guest->update(['permissions' => ['vehicles' => ['view']]]);

        $this->assertSame([], $guest->fresh()->permissions);
        $this->assertFalse(User::factory()->admin()->create()->can('delete', $guest));
    }

    #[Test]
    public function without_the_teams_module_nobody_is_limited_to_a_team(): void
    {
        $this->setModules(['teams' => false]);
        $employee = User::factory()->withPermissions(['vehicles' => ['view']])->create();
        Vehicle::factory()->for(Team::factory())->create(['plate_number' => '1-AAA-111']);

        $this->assertFalse($employee->isGuest());
        $this->actingAs($employee)->get('/vehicles')->assertOk()->assertSee('1-AAA-111');
    }
}
