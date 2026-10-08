<?php

namespace Tests\Feature;

use App\Filament\Resources\Teams\Pages\CreateTeam;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_location_holds_teams_vehicles_and_assets(): void
    {
        $location = Location::factory()->create(['street' => 'Rue de la Gare 5', 'postal_code' => '4000', 'city' => 'Liège', 'country' => 'België']);
        Team::factory()->create(['location_id' => $location->id]);
        Vehicle::factory()->create(['location_id' => $location->id]);

        $this->assertSame('Rue de la Gare 5, 4000 Liège, België', $location->addressLine());
        $this->assertCount(1, $location->teams);
        $this->assertCount(1, $location->vehicles);
    }

    #[Test]
    public function employees_only_see_the_locations_of_their_teams(): void
    {
        $luik = Location::factory()->create(['name' => 'Depot Luik']);
        $namen = Location::factory()->create(['name' => 'Depot Namen']);
        $employee = User::factory()->withPermissions(['locations' => ['view']])->inTeam(Team::factory()->create(['location_id' => $luik->id]))->create();

        $this->actingAs($employee)->get('/locations')->assertOk()->assertSee('Depot Luik')->assertDontSee('Depot Namen');
        $this->actingAs($employee)->get("/locations/{$namen->id}")->assertNotFound();
    }

    #[Test]
    public function the_location_field_only_shows_with_the_locations_module(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(CreateTeam::class)->assertFormFieldVisible('location_id');

        $this->setModules(['locations' => false]);

        Livewire::test(CreateTeam::class)->assertFormFieldHidden('location_id');
        $this->get('/admin/locations')->assertForbidden();
    }
}
