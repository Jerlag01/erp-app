<?php

namespace Tests\Feature;

use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Models\Asset;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_switched_off_module_is_closed_for_everyone_including_administrators(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create();

        $this->setModules(['fleet' => false]);

        $this->actingAs($admin)->get('/admin/vehicles')->assertForbidden();
        $this->actingAs($admin)->get("/vehicles/{$vehicle->id}")->assertForbidden();
        $this->actingAs($admin)->get('/admin/assets')->assertOk();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('/admin/vehicles"', false);
    }

    #[Test]
    public function each_module_works_on_its_own(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['fleet' => '/admin/vehicles', 'teams' => '/admin/teams', 'assets' => '/admin/assets'] as $module => $url) {
            $this->setModules(['fleet' => false, 'teams' => false, 'assets' => false, $module => true]);

            $this->actingAs($admin)->get($url)->assertOk();
            $this->actingAs($admin)->get("{$url}/create")->assertOk();
        }
    }

    #[Test]
    public function the_asset_form_only_links_to_teams_and_vehicles_when_those_modules_are_on(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(CreateAsset::class)
            ->assertFormFieldVisible('team_id')
            ->assertFormFieldVisible('vehicle_id');

        $this->setModules(['fleet' => false, 'teams' => false]);

        Livewire::test(CreateAsset::class)
            ->assertFormFieldHidden('team_id')
            ->assertFormFieldHidden('vehicle_id')
            ->assertFormFieldVisible('user_id');
    }

    #[Test]
    public function the_vehicle_form_only_links_to_a_team_when_teams_are_on(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(CreateVehicle::class)->assertFormFieldVisible('team_id');

        $this->setModules(['teams' => false]);

        Livewire::test(CreateVehicle::class)->assertFormFieldHidden('team_id');
    }

    #[Test]
    public function links_are_kept_while_a_module_is_off(): void
    {
        $admin = User::factory()->admin()->create();
        $team = Team::factory()->create();
        $vehicle = Vehicle::factory()->for($team)->create();
        $asset = Asset::factory()->for($team)->for($vehicle)->create();

        $this->setModules(['teams' => false]);
        Filament::setCurrentPanel('admin');

        Livewire::actingAs($admin)
            ->test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($team->id, $asset->fresh()->team_id);
        $this->assertSame('Renamed', $asset->fresh()->name);
    }
}
