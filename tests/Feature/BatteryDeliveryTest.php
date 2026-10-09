<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Filament\Admin\Pages\Settings;
use App\Filament\Resources\BatteryDeliveries\Pages\ManageBatteryDeliveries;
use App\Models\BatteryDelivery;
use App\Models\BatteryDossier;
use App\Models\BatteryModel;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BatteryDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        $this->team = Team::factory()->create();
    }

    protected function user(array $battery = ['view', 'create', 'update', 'delete']): User
    {
        return User::factory()->withPermissions(['projects' => ['view'], 'batteries' => $battery])->inTeam($this->team)->create();
    }

    protected function project(string $cow = '55MUN', string $city = 'Munkzwalm', int $number = 764): Project
    {
        $site = WorkSite::query()->where('cow_code', $cow)->first() ?? WorkSite::factory()->create(['cow_code' => $cow, 'city' => $city]);
        $project = Project::factory()->inCategory('604')->for($site)->create(['number' => $number, 'status' => ProjectStatus::Offer]);
        $project->teams()->attach($this->team);

        return $project;
    }

    #[Test]
    public function an_order_is_linked_to_its_604_project_and_takes_over_the_dossier(): void
    {
        $project = $this->project();
        BatteryDossier::factory()->create(['project_id' => $project->id, 'new_count' => 2, 'cells_per_battery' => 24]);
        $this->actingAs($this->user());

        Livewire::test(ManageBatteryDeliveries::class)
            ->mountAction('create')
            ->fillForm(['project_id' => $project->id])
            ->assertSchemaStateSet(['battery_model_id' => BatteryModel::query()->where('name', '7 OPzV 490')->value('id'), 'pieces' => 48])
            ->fillForm(['order_confirmation' => '4144900', 'planned_delivery_date' => '2026-11-03'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $delivery = BatteryDelivery::sole();
        $this->assertSame('55MUN - Munkzwalm', $delivery->location());
        $this->assertSame('764', $delivery->orderNumber());
        $this->assertSame(48, $delivery->pieces);

        // One line per project.
        Livewire::test(ManageBatteryDeliveries::class)
            ->callAction('create', ['project_id' => $project->id])
            ->assertHasActionErrors(['project_id']);
    }

    #[Test]
    public function ticking_delivered_stamps_the_system_date_and_moves_the_project_on(): void
    {
        $this->travelTo('2026-10-12 09:15:00');
        $project = $this->project();
        $delivery = BatteryDelivery::factory()->create(['project_id' => $project->id]);
        $user = $this->user();
        $this->actingAs($user);

        Livewire::test(ManageBatteryDeliveries::class)
            ->call('updateTableColumnState', 'delivered', (string) $delivery->getKey(), true);

        $delivery->refresh();
        $this->assertTrue($delivery->delivered);
        $this->assertSame('2026-10-12 09:15', $delivery->delivered_at->format('Y-m-d H:i'));
        $this->assertTrue($delivery->deliverer->is($user));
        $this->assertSame(ProjectStatus::ToBeExecuted, $project->fresh()->status);

        // Unticking clears the date; the project keeps its status.
        Livewire::test(ManageBatteryDeliveries::class)
            ->set('activeTab', 'delivered')
            ->call('updateTableColumnState', 'delivered', (string) $delivery->getKey(), false);

        $this->assertNull($delivery->fresh()->delivered_at);
        $this->assertSame(ProjectStatus::ToBeExecuted, $project->fresh()->status);
    }

    #[Test]
    public function the_status_after_delivery_comes_from_the_settings(): void
    {
        settings()->save('batteries', [...settings('batteries'), 'delivered_project_status' => ProjectStatus::Planned->value]);
        $delivery = BatteryDelivery::factory()->create(['project_id' => $this->project()->id]);

        $delivery->markDelivered($this->user());
        $this->assertSame(ProjectStatus::Planned, $delivery->project->fresh()->status);

        settings()->save('batteries', [...settings('batteries'), 'delivered_project_status' => null]);
        $other = BatteryDelivery::factory()->create(['project_id' => $this->project('56DOT', 'Dottignies', 785)->id]);
        $other->markDelivered($this->user());
        $this->assertSame(ProjectStatus::Offer, $other->project->fresh()->status);
    }

    #[Test]
    public function only_users_who_may_update_can_tick_and_prices_stay_hidden(): void
    {
        $delivery = BatteryDelivery::factory()->create(['project_id' => $this->project()->id, 'total_cost' => 11233.84]);
        $this->actingAs($this->user(['view']));

        $this->get('/battery-deliveries')->assertOk()->assertSee('55MUN - Munkzwalm')->assertDontSee('11.233,84');

        Livewire::test(ManageBatteryDeliveries::class)
            ->call('updateTableColumnState', 'delivered', (string) $delivery->getKey(), true);

        $this->assertFalse($delivery->fresh()->delivered);

        $this->actingAs($this->user(['view', 'prices']))->get('/battery-deliveries')->assertSee('11.233,84');

        // Another team's project is not listed.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view'], 'batteries' => ['view']])->inTeam()->create())
            ->get('/battery-deliveries')->assertOk()->assertDontSee('55MUN - Munkzwalm');
    }

    #[Test]
    public function the_overview_prints_like_the_excel(): void
    {
        BatteryDelivery::factory()->create(['project_id' => $this->project()->id]);
        $this->actingAs($this->user());

        $this->get(route('batteries.deliveries-pdf', ['tab' => 'all']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        Livewire::test(ManageBatteryDeliveries::class)->assertActionVisible('print');
    }

    #[Test]
    public function the_batteries_settings_tab_saves(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(Settings::class)
            ->fillForm([
                'batteries.delivered_project_status' => ProjectStatus::Planned->value,
                'batteries.hourly_rate' => 70.5,
                'batteries.contacts' => ['Kevin Cnop'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('planned', settings()->get('batteries.delivered_project_status'));
        $this->assertEquals(70.5, settings()->get('batteries.hourly_rate'));
        $this->assertSame('BAE', settings()->get('batteries.brand'), 'Untouched values are kept.');
    }
}
