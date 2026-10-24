<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Filament\Admin\Pages\Settings;
use App\Filament\Client\Resources\BatteryDeliveries\ClientBatteryDeliveryResource;
use App\Filament\Resources\BatteryDeliveries\Pages\ManageBatteryDeliveries;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Models\BatteryDelivery;
use App\Models\BatteryDossier;
use App\Models\BatteryModel;
use App\Models\ClientUser;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\ClientPortal;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $this->assertSame('0764', $delivery->orderNumber());
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
        $this->get(route('batteries.deliveries-pdf', ['tab' => 'all']))->assertForbidden();
        Livewire::test(ManageBatteryDeliveries::class)->assertActionHidden('print');

        $this->actingAs($this->user(['view', 'generate_pdf']));
        $this->get(route('batteries.deliveries-pdf', ['tab' => 'all']))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // In Dutch (Belgium), not in German.
        $html = view('batteries.pdf.deliveries', ['deliveries' => collect()])->render();
        $this->assertStringContainsString('Overzicht bestellingen', $html);
        $this->assertStringContainsString('Werkelijke leverdatum', $html);
        $this->assertStringNotContainsString('Übersicht', $html);
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

    #[Test]
    public function the_order_confirmation_and_delivery_note_are_uploaded_and_seen_only_with_the_permissions(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $delivery = BatteryDelivery::factory()->create(['project_id' => $project->id, 'order_confirmation' => '4141234']);

        // Without the document permissions: no column, no upload, no download, no tab on the project.
        $this->actingAs($this->user());
        Livewire::test(ManageBatteryDeliveries::class)
            ->assertTableColumnHidden('documents')
            ->assertTableActionHidden('documents', $delivery);
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertDontSee(__('erp.batteries.delivery_documents.tab'));

        // Uploading: one zone per document.
        $this->actingAs($this->user(['view', 'update', 'documents', 'documents_upload']));
        Livewire::test(ManageBatteryDeliveries::class)
            ->callTableAction('documents', $delivery, [
                'order' => UploadedFile::fake()->create('bestelling-BAE.pdf', 30, 'application/pdf'),
                'confirmation' => UploadedFile::fake()->create('bevestiging.pdf', 30, 'application/pdf'),
            ])
            ->assertHasNoTableActionErrors();

        $delivery->refresh();
        $this->assertSame('bestelling-BAE.pdf', $delivery->documentName('order'));
        $this->assertTrue($delivery->hasDocument('confirmation'));
        $this->assertFalse($delivery->hasDocument('delivery_note'));

        // The delivery note comes later; the order can be removed again.
        $order = $delivery->order_document;
        Livewire::test(ManageBatteryDeliveries::class)
            ->callTableAction('documents', $delivery, ['delivery_note' => UploadedFile::fake()->create('leveringsbon.pdf', 30, 'application/pdf'), 'order_remove' => true])
            ->assertHasNoTableActionErrors();
        $delivery->refresh();
        $this->assertTrue($delivery->hasDocument('delivery_note'));
        $this->assertFalse($delivery->hasDocument('order'));
        Storage::disk('local')->assertMissing($order);

        // Linked to the project: a tab with the deliveries and their documents.
        $this->get(route('batteries.delivery-document', ['delivery' => $delivery, 'kind' => 'delivery_note']))->assertOk()->assertDownload('leveringsbon.pdf');
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertSee(__('erp.batteries.delivery_documents.tab'));

        // Seeing without uploading.
        $this->actingAs($this->user(['view', 'documents']));
        Livewire::test(ManageBatteryDeliveries::class)->assertTableColumnVisible('documents')->assertTableActionHidden('documents', $delivery);
        $this->get(route('batteries.delivery-document', ['delivery' => $delivery, 'kind' => 'confirmation']))->assertOk();

        // Others are refused, and the client portal never shows them.
        $this->actingAs($this->user());
        $this->get(route('batteries.delivery-document', ['delivery' => $delivery, 'kind' => 'confirmation']))->assertForbidden();
        $client = ClientUser::factory()->withPermissions([ClientPortal::BATTERY_DELIVERIES, ClientPortal::ALL_PROJECTS])->create();
        $this->actingAs($client, ClientPortal::GUARD);
        Filament::setCurrentPanel(ClientPortal::PANEL);
        $this->get(ClientBatteryDeliveryResource::getUrl())->assertOk()->assertSee('4141234')->assertDontSee('leveringsbon')->assertDontSee('bevestiging.pdf');
        $this->get(route('batteries.delivery-document', ['delivery' => $delivery, 'kind' => 'confirmation']))->assertForbidden();
    }
}
