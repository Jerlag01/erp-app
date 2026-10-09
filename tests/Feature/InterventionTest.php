<?php

namespace Tests\Feature;

use App\Enums\InterventionCategoryKind;
use App\Enums\InterventionReportStatus;
use App\Enums\MaterialDirection;
use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\InterventionReports\Pages\ViewInterventionReport;
use App\Filament\Resources\Interventions\Pages\ListInterventions;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Models\InterventionCategory;
use App\Models\InterventionReport;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InterventionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Filament::setCurrentPanel('app');
    }

    protected function category(string $name): InterventionCategory
    {
        return InterventionCategory::query()->where('name', $name)->sole();
    }

    protected function intervention(array $attributes = [], ?Team $team = null): Project
    {
        $site = WorkSite::query()->where('cow_code', '02GAM')->first() ?? WorkSite::factory()->create(['cow_code' => '02GAM']);
        $project = Project::factory()->inCategory('600')->for($site)->create([
            'work_order_number' => 'WO'.fake()->unique()->numerify('######'),
            'status' => ProjectStatus::ToBeExecuted,
            ...$attributes,
        ]);

        if ($team) {
            $project->teams()->attach($team);
        }

        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    protected function closeData(Project $project, array $overrides = []): array
    {
        return [
            'categories' => [$this->category('CONNECT')->id],
            'work_site_id' => $project->work_site_id,
            'room' => 'Technical room 2',
            'hours_worked' => 3.5,
            'materials' => [['description' => 'XVB 3G2.5', 'quantity' => 25, 'direction' => 'used']],
            'positions' => [['board_name' => 'td-01 ', 'current' => 'DC', 'device' => 'Q', 'number' => '12', 'supply' => 'B']],
            'modified_boards' => ['TD-01', 'td-01', 'Main board'],
            'remarks' => 'Old cable left in the room.',
            ...$overrides,
        ];
    }

    #[Test]
    public function category_600_and_the_default_intervention_categories_exist(): void
    {
        $category = ProjectCategory::query()->where('code', '600')->sole();
        $this->assertTrue($category->numbering->isIntervention());

        $this->assertSame(['PREPARE', 'CONNECT', 'DECONNECT'], InterventionCategory::query()->ofKind(InterventionCategoryKind::Category)->pluck('name')->all());
        $this->assertTrue($this->category('DECONNECT')->frees_material);
        $colibri = $this->category('COLIBRI');
        $this->assertSame(InterventionCategoryKind::Subcategory, $colibri->kind);
        $this->assertSame(['PHASE 1', 'PHASE 2'], $colibri->phaseList());
        $this->assertSame(['FRM220UB', 'FRM220 FIRE'], $colibri->tagList());

        $this->assertSame('600-001-02GAM', $this->intervention()->reference);
    }

    #[Test]
    public function a_new_intervention_needs_a_unique_wo_number(): void
    {
        $employee = User::factory()->withPermissions(['projects' => ['view', 'create']])->inTeam()->create();
        $site = WorkSite::factory()->create();
        $this->intervention(['work_order_number' => '123456']);
        $this->actingAs($employee);
        $category = ProjectCategory::query()->where('code', '600')->value('id');
        $colibri = $this->category('COLIBRI');

        Livewire::test(CreateProject::class)
            ->fillForm(['project_category_id' => $category, 'work_site_id' => $site->id])
            ->call('create')
            ->assertHasFormErrors(['work_order_number' => 'required']);

        Livewire::test(CreateProject::class)
            ->fillForm(['project_category_id' => $category, 'work_site_id' => $site->id, 'work_order_number' => 'WO 123456'])
            ->call('create')
            ->assertHasFormErrors(['work_order_number']);

        Livewire::test(CreateProject::class)
            ->fillForm([
                'project_category_id' => $category,
                'work_site_id' => $site->id,
                'work_order_number' => 'wo 654321',
                'interventionCategories' => [$this->category('PREPARE')->id, $this->category('CONNECT')->id],
                'intervention_subcategory_id' => $colibri->id,
            ])
            ->fillForm(['intervention_phase' => 'PHASE 2', 'intervention_tags' => ['FRM220 FIRE']])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->where('work_order_number', '654321')->sole();
        $this->assertSame(ProjectStatus::ToBeExecuted, $project->status);
        $this->assertSame(['PREPARE', 'CONNECT'], $project->interventionCategories->pluck('name')->all());
        $this->assertSame('COLIBRI · PHASE 2 · FRM220 FIRE', $project->interventionSubcategoryLabel());
        $this->assertSame(0, $project->poNumbers()->count(), 'Interventions get their PO number when approved.');
    }

    #[Test]
    public function a_team_member_closes_the_intervention_for_review(): void
    {
        Repeater::fake();
        $admin = User::factory()->admin()->create();
        $team = Team::factory()->create();
        $employee = User::factory()->withPermissions(['projects' => ['view']])->inTeam($team)->create();
        $colleague = User::factory()->create();
        $project = $this->intervention(team: $team);
        $this->actingAs($employee);

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('closeIntervention', $this->closeData($project, ['co_workers' => [$colleague->id]]))
            ->assertHasNoActionErrors();

        $report = InterventionReport::sole();
        $this->assertSame(InterventionReportStatus::Pending, $report->status);
        $this->assertSame(ProjectStatus::Executed, $project->fresh()->status);
        $this->assertSame('02GAM', $report->cow_code);
        $this->assertSame(['CONNECT'], $report->categoryNames());
        $this->assertSame(['TD-01', 'MAIN BOARD'], $report->boardNames());
        $this->assertSame('3:30', $report->hoursLabel());
        $this->assertNotNull($report->executed_at);
        $this->assertSame([$employee->name, $colleague->name], $report->workerNames());
        $this->assertSame('TD-01 · DC · Q12 · '.__('erp.interventions.supply_side', ['side' => 'B']), $report->positions->sole()->label());
        $this->assertSame(MaterialDirection::Used, $report->materials->sole()->direction);
        Storage::disk('local')->assertExists($report->pdf_path);
        $this->assertSame(1, $admin->notifications()->count());

        // Closed: not again until an administrator sends it back.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('closeIntervention');
        // Employees without finance rights don't get the PDF.
        $this->get(route('interventions.pdf', $report))->assertForbidden();
    }

    #[Test]
    public function freed_material_needs_a_deconnect_category(): void
    {
        Repeater::fake();
        $project = $this->intervention();
        $this->actingAs(User::factory()->admin()->create());
        $freed = ['materials' => [['description' => 'Old breaker', 'quantity' => 1, 'direction' => 'freed']]];

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('closeIntervention', $this->closeData($project, $freed))
            ->assertHasActionErrors(['materials.0.direction']);

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('closeIntervention', $this->closeData($project, [...$freed, 'categories' => [$this->category('DECONNECT')->id]]))
            ->assertHasNoActionErrors();

        $this->assertSame(MaterialDirection::Freed, InterventionReport::sole()->materials->sole()->direction);
    }

    #[Test]
    public function an_administrator_approves_with_the_po_number_or_sends_it_back(): void
    {
        Repeater::fake();
        $admin = User::factory()->admin()->create();
        $closer = User::factory()->create();
        $project = $this->intervention(['status' => ProjectStatus::Executed]);
        $report = InterventionReport::factory()->for($project)->create(['closed_by' => $closer->id]);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewInterventionReport::class, ['record' => $report->getRouteKey()])
            ->callAction('reject', ['note' => 'Room is missing the floor.'])
            ->assertHasNoActionErrors();

        $this->assertSame(InterventionReportStatus::Rejected, $report->fresh()->status);
        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
        $this->assertSame(1, $closer->notifications()->count());

        $report->refresh()->update(['status' => InterventionReportStatus::Pending]);

        Livewire::test(ViewInterventionReport::class, ['record' => $report->getRouteKey()])
            ->callAction('approve', ['po_number' => '12345'])
            ->assertHasActionErrors(['po_number']);

        Livewire::test(ViewInterventionReport::class, ['record' => $report->getRouteKey()])
            ->callAction('approve', ['po_number' => '4500123456'])
            ->assertHasNoActionErrors();

        $report->refresh();
        $this->assertSame(InterventionReportStatus::Approved, $report->status);
        $this->assertSame('4500123456', $report->po_number);
        $this->assertSame(['4500123456'], $project->poNumbers()->pluck('number')->all());
        $this->assertSame(ProjectStatus::InvoicingToDo, $project->fresh()->status);

        $this->get(route('interventions.pdf', $report))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/admin/intervention-reviews')->assertOk();
        $this->get('/admin/intervention-categories')->assertOk();
        $this->get('/admin/electrical-boards')->assertNotFound();
    }

    #[Test]
    public function the_interventions_list_shows_what_the_user_may_see(): void
    {
        Filament::setCurrentPanel('app');
        $team = Team::factory()->create();
        $member = User::factory()->withPermissions(['projects' => ['view']])->inTeam($team)->create();
        $planned = User::factory()->inTeam()->create();
        $ours = $this->intervention(team: $team);
        $other = $this->intervention();
        PlanningItem::factory()->for($planned)->forProject($other)->create();
        $normalProject = Project::factory()->inCategory('604')->create();
        $normalProject->teams()->attach($team);

        $this->actingAs($member);
        Livewire::test(ListInterventions::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$ours])
            ->assertCanNotSeeTableRecords([$other, $normalProject]);

        $this->actingAs($planned);
        $this->get('/interventions')->assertOk();
        Livewire::test(ListInterventions::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$ours]);

        $this->actingAs(User::factory()->inTeam()->create())->get('/interventions')->assertForbidden();
    }

    #[Test]
    public function without_the_module_category_600_is_an_ordinary_project_category(): void
    {
        $this->setModules(['interventions' => false]);
        $admin = User::factory()->admin()->create();
        $project = $this->intervention();
        $this->actingAs($admin);

        $this->get('/interventions')->assertForbidden();
        $this->get('/admin/intervention-reviews')->assertForbidden();
        $this->get('/admin/intervention-categories')->assertForbidden();
        $this->get("/projects/{$project->id}")->assertOk()->assertSee($project->reference);
        $this->get('/projects')->assertOk()->assertSee($project->reference);

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('closeIntervention');
    }
}
