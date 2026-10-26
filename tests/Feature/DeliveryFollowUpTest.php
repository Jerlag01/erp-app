<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Filament\Resources\BoardDeliveries\BoardDeliveryResource;
use App\Filament\Resources\BoardDeliveries\Pages\ManageBoardDeliveries;
use App\Filament\Resources\EnersysDeliveries\EnersysDeliveryResource;
use App\Filament\Resources\EnersysDeliveries\Pages\ManageEnersysDeliveries;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\DeliveryFollowUpsRelationManager;
use App\Models\BoardSupplier;
use App\Models\DeliveryFollowUp;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\Modules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeliveryFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        $this->team = Team::factory()->create();
    }

    /**
     * @param  array<string, list<string>>  $permissions
     */
    protected function user(array $permissions): User
    {
        return User::factory()->withPermissions(['projects' => ['view'], ...$permissions])->inTeam($this->team)->create();
    }

    protected function project(string $category, ProjectStatus $status = ProjectStatus::Offer): Project
    {
        $project = Project::factory()->inCategory($category)->create(['status' => $status]);
        $project->teams()->attach($this->team);

        return $project;
    }

    #[Test]
    public function an_enersys_delivery_is_followed_up_and_moves_its_project_to_be_planned(): void
    {
        $this->travelTo('2026-11-03 10:00:00');
        $project = $this->project('605');
        $project->poNumbers()->create(['number' => '4500123456']);
        $user = $this->user(['enersys' => ['view', 'create', 'update']]);
        $this->actingAs($user);

        Livewire::test(ManageEnersysDeliveries::class)
            ->mountAction('create')
            ->fillForm(['project_id' => $project->id])
            ->assertSchemaStateSet(['po_number' => '4500123456'])
            ->fillForm(['delivery_date' => '2026-11-10'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $delivery = DeliveryFollowUp::sole();
        $this->assertSame(DeliveryFollowUp::ENERSYS, $delivery->kind);

        // One line per project; and only 605 projects.
        Livewire::test(ManageEnersysDeliveries::class)->callAction('create', ['project_id' => $project->id])->assertHasActionErrors(['project_id']);
        Livewire::test(ManageEnersysDeliveries::class)->callAction('create', ['project_id' => $this->project('610')->id])->assertHasActionErrors(['project_id']);

        Livewire::test(ManageEnersysDeliveries::class)
            ->assertCanSeeTableRecords([$delivery])
            ->call('updateTableColumnState', 'delivered', (string) $delivery->getKey(), true);

        $delivery->refresh();
        $this->assertTrue($delivery->delivered);
        $this->assertSame('2026-11-03', $delivery->delivered_on->toDateString());
        $this->assertSame(ProjectStatus::ToBePlanned, $project->refresh()->status);

        // A project that is already further keeps its status.
        $planned = DeliveryFollowUp::factory()->create(['project_id' => $this->project('605', ProjectStatus::Planned)->id]);
        $planned->markDelivered($user);
        $this->assertSame(ProjectStatus::Planned, $planned->project->refresh()->status);
    }

    #[Test]
    public function the_enersys_list_needs_the_enersys_permissions_and_module(): void
    {
        $this->actingAs($this->user(['batteries' => ['view']]));
        $this->get(EnersysDeliveryResource::getUrl('index'))->assertForbidden();

        $this->actingAs($this->user(['enersys' => ['view']]));
        $this->get(EnersysDeliveryResource::getUrl('index'))->assertOk();

        // Only seeing: the check box cannot be ticked.
        $delivery = DeliveryFollowUp::factory()->create(['project_id' => $this->project('605')->id]);
        Livewire::test(ManageEnersysDeliveries::class)->call('updateTableColumnState', 'delivered', (string) $delivery->getKey(), true);
        $this->assertFalse($delivery->refresh()->delivered);

        settings()->save('modules', [...settings('modules'), Modules::ENERSYS => false]);
        $this->get(EnersysDeliveryResource::getUrl('index'))->assertForbidden();
    }

    #[Test]
    public function every_board_supplier_has_its_own_list_with_its_layout(): void
    {
        $user = $this->user(['board_deliveries' => ['view', 'create', 'update']]);
        $this->actingAs($user);
        $elp = BoardSupplier::query()->where('name', 'ELP')->sole();
        $cet = BoardSupplier::query()->where('name', 'CE+T')->sole();
        $simple = DeliveryFollowUp::factory()->board('ELP')->create(['project_id' => $this->project('620', ProjectStatus::ToBePlanned)->id, 'po_number' => '4500000001']);
        $configured = DeliveryFollowUp::factory()->board('CE+T')->create(['project_id' => $this->project('630')->id, 'po_number' => '4500000002']);

        // ELP: project, PO number, delivery date, delivered.
        Livewire::test(ManageBoardDeliveries::class)
            ->set('activeTab', 'supplier-'.$elp->id)
            ->assertCanSeeTableRecords([$simple])
            ->assertCanNotSeeTableRecords([$configured])
            ->assertTableColumnVisible('delivered')
            ->assertTableColumnHidden('configured_on')
            ->assertTableColumnHidden('completed')
            ->call('updateTableColumnState', 'delivered', (string) $simple->getKey(), true);

        // No status change for boards.
        $this->assertTrue($simple->refresh()->delivered);
        $this->assertSame(ProjectStatus::ToBePlanned, $simple->project->refresh()->status);

        // CE+T: PO number, project, expected and actual delivery, configuration date, completed.
        Livewire::test(ManageBoardDeliveries::class)
            ->set('activeTab', 'supplier-'.$cet->id)
            ->assertCanSeeTableRecords([$configured])
            ->assertTableColumnVisible('po_lead')
            ->assertTableColumnVisible('delivered_on')
            ->assertTableColumnVisible('configured_on')
            ->assertTableColumnHidden('delivered')
            ->call('updateTableColumnState', 'delivered_on', (string) $configured->getKey(), '2026-11-12')
            ->call('updateTableColumnState', 'configured_on', (string) $configured->getKey(), '2026-11-14')
            ->call('updateTableColumnState', 'completed', (string) $configured->getKey(), true);

        $configured->refresh();
        $this->assertSame('2026-11-12', $configured->delivered_on->toDateString());
        $this->assertSame('2026-11-14', $configured->configured_on->toDateString());
        $this->assertTrue($configured->completed);
        $this->assertNotNull($configured->completed_at);
        $this->assertSame(ProjectStatus::Offer, $configured->project->refresh()->status);

        // The date fields in the list show the saved dates (the browser's date field needs Y-m-d).
        Livewire::test(ManageBoardDeliveries::class)
            ->set('activeTab', 'supplier-'.$cet->id)
            ->assertTableColumnStateSet('delivered_on', '2026-11-12', $configured)
            ->assertTableColumnStateSet('configured_on', '2026-11-14', $configured);

        // New lines: 610–650 projects only.
        Livewire::test(ManageBoardDeliveries::class)
            ->set('activeTab', 'supplier-'.$cet->id)
            ->callAction('create', ['board_supplier_id' => $cet->id, 'project_id' => $this->project('605')->id])
            ->assertHasActionErrors(['project_id']);
        Livewire::test(ManageBoardDeliveries::class)
            ->callAction('create', ['board_supplier_id' => $cet->id, 'project_id' => $this->project('640')->id, 'po_number' => '4500000003', 'completed' => true])
            ->assertHasNoActionErrors();
        $this->assertNotNull(DeliveryFollowUp::query()->where('po_number', '4500000003')->sole()->completed_at);
    }

    #[Test]
    public function the_boards_module_has_its_own_permissions_and_the_project_shows_its_follow_ups(): void
    {
        $project = $this->project('610');
        DeliveryFollowUp::factory()->board('P&V panels')->create(['project_id' => $project->id]);

        $this->actingAs($this->user(['enersys' => ['view']]));
        $this->get(BoardDeliveryResource::getUrl('index'))->assertForbidden();
        $this->assertFalse(DeliveryFollowUpsRelationManager::canViewForRecord($project, ViewProject::class));

        $this->actingAs($this->user(['board_deliveries' => ['view']]));
        $this->get(BoardDeliveryResource::getUrl('index'))->assertOk();
        $this->assertTrue(DeliveryFollowUpsRelationManager::canViewForRecord($project, ViewProject::class));

        settings()->save('modules', [...settings('modules'), Modules::BOARD_DELIVERIES => false]);
        $this->get(BoardDeliveryResource::getUrl('index'))->assertForbidden();
    }
}
