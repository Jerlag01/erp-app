<?php

namespace Tests\Feature;

use App\Filament\App\Widgets\MyAssets;
use App\Filament\App\Widgets\MyTeams;
use App\Filament\App\Widgets\MyVehicles;
use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\OrderReference;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\VacationRequest;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_are_sent_to_the_login_pages(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/login')->assertOk();
        $this->get('/admin/login')->assertOk();
    }

    #[Test]
    public function administrators_can_use_both_panels(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/')->assertOk();
    }

    #[Test]
    public function employees_can_use_the_employee_panel_only(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get('/')->assertOk();
        $this->actingAs($employee)->get('/admin')->assertForbidden();
    }

    #[Test]
    public function inactive_users_and_users_without_a_role_are_refused(): void
    {
        $this->actingAs(User::factory()->inactive()->create())->get('/')->assertForbidden();
        $this->actingAs(User::factory()->create(['role_id' => null]))->get('/')->assertForbidden();
    }

    #[Test]
    public function the_admin_only_pages_are_closed_to_employees(): void
    {
        $employee = User::factory()->withPermissions(['vehicles' => ['view', 'create', 'update', 'delete']])->create();

        foreach (['/admin/users', '/admin/roles', '/admin/settings', '/admin/asset-categories'] as $url) {
            $this->actingAs($employee)->get($url)->assertForbidden();
        }
    }

    #[Test]
    public function every_admin_page_renders(): void
    {
        $admin = User::factory()->admin()->create();
        VacationRequest::factory()->for($admin)->create();
        VacationRequest::factory()->approved()->create(['start_date' => '2026-12-21', 'end_date' => '2026-12-24']);
        OrderReference::factory()->for($admin)->create(['supplier' => 'Rexel']);

        $urls = [
            '/admin', '/admin/users', '/admin/users/create', '/admin/roles', '/admin/roles/create',
            '/admin/settings', '/admin/asset-categories', '/admin/teams', '/admin/teams/create',
            '/admin/vehicles', '/admin/vehicles/create', '/admin/assets', '/admin/assets/create',
            '/admin/asset-logs', '/admin/profile', '/admin/vacation-requests', '/admin/holidays',
            '/admin/order-references', '/my-vacation', '/vacation-requests', '/order-references',
            '/admin/roles/'.Role::guest()->id.'/edit',
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    #[Test]
    public function record_pages_render_with_their_linked_modules(): void
    {
        $admin = User::factory()->admin()->create();
        $team = Team::factory()->create(['leader_id' => $admin->id]);
        $team->members()->attach($admin);
        $vehicle = Vehicle::factory()->for($team)->create(['driver_id' => $admin->id]);
        $asset = Asset::factory()->for($team)->for($vehicle)->create();
        $damage = AssetLog::factory()->for($asset)->damage()->create(['title' => 'Snoer gebroken']);
        AssetLog::factory()->repairOf($damage)->create(['title' => 'Snoer vervangen']);

        foreach (['/admin', ''] as $prefix) {
            foreach (["/teams/{$team->id}", "/teams/{$team->id}/edit", "/vehicles/{$vehicle->id}", "/vehicles/{$vehicle->id}/edit", "/assets/{$asset->id}/edit", '/asset-logs'] as $url) {
                $this->actingAs($admin)->get($prefix.$url)->assertOk();
            }

            $this->actingAs($admin)->get("{$prefix}/assets/{$asset->id}")->assertOk()->assertSee($asset->asset_tag);
        }

        $this->actingAs($admin)->get("/admin/users/{$admin->id}/edit")->assertOk();
        $this->actingAs($admin)->get("/admin/roles/{$admin->role_id}/edit")->assertOk();
    }

    #[Test]
    public function the_employee_dashboard_shows_their_own_teams_vehicles_and_equipment(): void
    {
        $employee = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Ploeg Luik']);
        $team->members()->attach($employee);
        Vehicle::factory()->for($team)->create(['plate_number' => '1-ERP-001']);
        Asset::factory()->create(['user_id' => $employee->id, 'asset_tag' => 'EQ-7777']);

        $this->actingAs($employee)->get('/')
            ->assertOk()
            ->assertSeeLivewire(MyTeams::class)
            ->assertSeeLivewire(MyVehicles::class)
            ->assertSeeLivewire(MyAssets::class);
    }
}
