<?php

namespace Tests\Feature;

use App\Actions\Interventions\CloseIntervention;
use App\Actions\Interventions\ReviewIntervention;
use App\Enums\InterventionCategoryKind;
use App\Enums\InterventionReportStatus;
use App\Enums\MaterialDirection;
use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\InterventionReports\Pages\ViewInterventionReport;
use App\Filament\Resources\Interventions\Pages\ListInterventions;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\RelationManagers\WasteReportsRelationManager;
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
use Illuminate\Validation\ValidationException;
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
            'intervention_due_on' => today()->addWeeks(2)->toDateString(),
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

        $this->assertSame('600-0001-02GAM', $this->intervention()->reference);
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
            // The WO number and the due date are both required.
            ->assertHasFormErrors(['work_order_number' => 'required', 'intervention_due_on' => 'required']);

        Livewire::test(CreateProject::class)
            ->fillForm(['project_category_id' => $category, 'work_site_id' => $site->id, 'work_order_number' => 'WO 123456'])
            ->call('create')
            ->assertHasFormErrors(['work_order_number']);

        Livewire::test(CreateProject::class)
            ->fillForm([
                'project_category_id' => $category,
                'work_site_id' => $site->id,
                'work_order_number' => 'wo 654321',
                'intervention_due_on' => '2026-12-31',
                'short_description' => 'Storing gelijkrichter',
                'interventionCategories' => [$this->category('PREPARE')->id, $this->category('CONNECT')->id],
                'intervention_subcategory_id' => $colibri->id,
            ])
            ->fillForm(['intervention_phase' => 'PHASE 2', 'intervention_tags' => ['FRM220 FIRE']])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->where('work_order_number', '654321')->sole();
        $this->assertSame(ProjectStatus::ToBeExecuted, $project->status);
        $this->assertSame('2026-12-31', $project->intervention_due_on->toDateString());
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
    public function an_intervention_on_several_cow_codes_has_a_form_per_cow_code(): void
    {
        Repeater::fake();
        $admin = User::factory()->admin()->create();
        $team = Team::factory()->create();
        $employee = User::factory()->withPermissions(['projects' => ['view', 'update']])->inTeam($team)->create();
        $project = $this->intervention(team: $team);
        $extra = WorkSite::factory()->create(['cow_code' => '02NO1', 'city' => 'Zaventem']);
        $this->actingAs($employee);

        // The extra COW code is set on the project.
        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->fillForm(['interventionWorkSites' => [$extra->id]])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['02NO1'], $project->fresh()->interventionWorkSites->pluck('cow_code')->all());

        Livewire::test(ListInterventions::class)->set('activeTab', 'open')->assertCanSeeTableRecords([$project]);

        // First COW code: the project is not executed yet.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertActionHasLabel('closeIntervention', __('erp.interventions.close_site'))
            ->callAction('closeIntervention', $this->closeData($project))
            ->assertHasNoActionErrors();
        $this->assertSame(ProjectStatus::ToBeExecuted, $project->fresh()->status);

        // That one is closed now: closing it again is refused, the other one is left.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('closeIntervention', $this->closeData($project))
            ->assertHasActionErrors(['work_site_id']);

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->mountAction('closeIntervention')
            ->assertSchemaStateSet(['work_site_id' => $extra->id], 'mountedActionSchema0')
            ->setActionData($this->closeData($project, ['work_site_id' => $extra->id]))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $reports = InterventionReport::query()->orderBy('id')->get();
        $this->assertSame(['02GAM', '02NO1'], $reports->pluck('cow_code')->all());
        $this->assertNotSame($reports[0]->pdf_path, $reports[1]->pdf_path);
        $this->assertSame(ProjectStatus::Executed, $project->fresh()->status);
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('closeIntervention');

        // Approving the first one is not enough for invoicing; both are.
        $this->actingAs($admin);
        $review = app(ReviewIntervention::class);
        $review->approve($reports[0], $admin);
        $this->assertSame(ProjectStatus::Executed, $project->fresh()->status);

        // A rejected COW code can be closed again on its own.
        $review->reject($reports[1], $admin, 'Wrong room');
        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
        $this->actingAs($employee);
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->mountAction('closeIntervention')
            ->assertSchemaStateSet(['work_site_id' => $extra->id, 'room' => 'Technical room 2'], 'mountedActionSchema0')
            ->setActionData(['room' => 'Room 3'])
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertSame(2, InterventionReport::query()->count());
        $this->assertSame('Room 3', $reports[1]->fresh()->room);

        $review->approve($reports[1]->fresh(), $admin);
        $this->assertSame(ProjectStatus::InvoicingToDo, $project->fresh()->status);

        $this->actingAs($admin);
        Livewire::test(ListInterventions::class)->set('activeTab', 'approved')->assertCanSeeTableRecords([$project]);
        Livewire::test(ListInterventions::class)->set('activeTab', 'open')->assertCanNotSeeTableRecords([$project]);
        Livewire::test(ListInterventions::class)->set('activeTab', 'all')->searchTable('02NO1')->assertCanSeeTableRecords([$project]);
        $this->get(ProjectResource::getUrl('view', ['record' => $project]))->assertOk()->assertSee('02NO1 · Zaventem');
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
    public function an_administrator_approves_without_asking_a_po_number_or_sends_it_back(): void
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

        // No PO number is asked any more: the project's own (if any) goes on the report.
        $project->poNumbers()->create(['number' => '4500123456']);
        Livewire::test(ViewInterventionReport::class, ['record' => $report->getRouteKey()])
            ->mountAction('approve')
            ->assertSchemaComponentDoesNotExist('po_number', 'mountedActionSchema0')
            ->setActionData(['note' => 'OK'])
            ->callMountedAction()
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

    #[Test]
    public function the_po_number_can_be_entered_on_creation_with_the_permission(): void
    {
        $site = WorkSite::factory()->create();
        $category = ProjectCategory::query()->where('code', '600')->value('id');
        $data = fn (string $wo): array => [
            'project_category_id' => $category,
            'work_site_id' => $site->id,
            'work_order_number' => $wo,
            'intervention_due_on' => today()->addWeek()->toDateString(),
            'short_description' => 'Storing gelijkrichter',
            'interventionCategories' => [$this->category('PREPARE')->id],
            'intervention_subcategory_id' => $this->category('COLIBRI')->id,
        ];

        // Without "projects.intervention_po" the PO numbers are not offered.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view', 'create']])->inTeam()->create());
        Livewire::test(CreateProject::class)
            ->fillForm($data('111111'))
            ->assertSchemaComponentHidden('poNumbers');

        $this->actingAs(User::factory()->withPermissions(['projects' => ['view', 'create', 'intervention_po']])->inTeam()->create());
        Livewire::test(CreateProject::class)
            ->fillForm($data('222222'))
            ->assertSchemaComponentVisible('poNumbers')
            ->fillForm(['poNumbers' => [['number' => '4500999999']]])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->where('work_order_number', '222222')->sole();
        $this->assertSame(['4500999999'], $project->poNumbers()->pluck('number')->all());

        // Approving puts that PO number on the report, without asking it again.
        $report = InterventionReport::factory()->for($project)->create(['closed_by' => User::factory()->create()->id]);
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');
        Livewire::test(ViewInterventionReport::class, ['record' => $report->getRouteKey()])
            ->callAction('approve')
            ->assertHasNoActionErrors();
        $this->assertSame('4500999999', $report->fresh()->po_number);
    }

    #[Test]
    public function a_po_number_can_be_added_to_a_600_project_at_any_moment(): void
    {
        $team = Team::factory()->create();
        $project = $this->intervention(['status' => ProjectStatus::InProgress], $team);

        // Without the permission: no button, no PO numbers on the edit form.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view', 'update']])->inTeam($team)->create());
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('addPoNumber');
        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])->assertSchemaComponentHidden('poNumbers');

        // With it: from the project page, whatever the status, and on the edit form.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view', 'update', 'intervention_po']])->inTeam($team)->create());
        foreach ([ProjectStatus::InProgress, ProjectStatus::Executed] as $i => $status) {
            $project->refresh()->update(['status' => $status]);
            Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
                ->callAction('addPoNumber', ['number' => '450000000'.$i])
                ->assertHasNoActionErrors();
        }
        $this->assertSame(['4500000000', '4500000001'], $project->poNumbers()->orderBy('number')->pluck('number')->all());

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('addPoNumber', ['number' => '4500000000'])
            ->assertHasActionErrors(['number' => 'unique']);
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('addPoNumber', ['number' => '12345'])
            ->assertHasActionErrors(['number']);

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])->assertSchemaComponentVisible('poNumbers');
    }

    #[Test]
    public function colibri_phase_2_interventions_get_the_waste_report_zone(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('app');
        $colibri = $this->category('COLIBRI');

        $this->assertTrue(WasteReportsRelationManager::canViewForRecord($this->intervention(['intervention_subcategory_id' => $colibri->id, 'intervention_phase' => 'PHASE 2']), ViewProject::class));
        $this->assertFalse(WasteReportsRelationManager::canViewForRecord($this->intervention(['intervention_subcategory_id' => $colibri->id, 'intervention_phase' => 'PHASE 1']), ViewProject::class));
        $this->assertFalse(WasteReportsRelationManager::canViewForRecord($this->intervention(), ViewProject::class));
    }

    #[Test]
    public function a_battery_cell_replacement_asks_the_replaced_cells_and_the_full_battery(): void
    {
        Repeater::fake();
        $cells = InterventionCategory::factory()->create(['kind' => InterventionCategoryKind::Category, 'name' => 'CELVERVANGING', 'measures_cells' => true]);
        $team = Team::factory()->create();
        $employee = User::factory()->withPermissions(['projects' => ['view']])->inTeam($team)->create();
        $project = $this->intervention(team: $team);
        $this->actingAs($employee);
        $full = array_map(fn (int $cell): float => 2.2 + $cell / 1000, range(1, 24));
        $measurement = [
            'replaced_count' => 2,
            'replaced_cells' => [['cell' => 17, 'voltage' => '2.231', 'density' => '1.240'], ['cell' => 3, 'voltage' => 2.228, 'density' => null]],
            'full_cells' => $full,
        ];

        // An ordinary category does not ask it.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->mountAction('closeIntervention')
            ->assertSchemaComponentHidden('replaced_count', 'mountedActionSchema0');

        // The cell replacement does: without the measurement it can not be closed.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('closeIntervention', $this->closeData($project, ['categories' => [$cells->id]]))
            ->assertHasActionErrors(['replaced_count' => 'required']);

        try {
            app(CloseIntervention::class)->handle($project, $this->closeData($project, ['categories' => [$cells->id], ...$measurement, 'replaced_count' => 3]), $employee);
            $this->fail('The number of replaced cells and the rows must match.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('replaced_cells', $exception->errors());
        }

        try {
            app(CloseIntervention::class)->handle($project, $this->closeData($project, ['categories' => [$cells->id], ...$measurement, 'full_cells' => array_slice($full, 0, 23)]), $employee);
            $this->fail('The full battery needs 24 cells.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('full_cells.23', $exception->errors());
        }

        $report = app(CloseIntervention::class)->handle($project, $this->closeData($project, ['categories' => [$cells->id], ...$measurement]), $employee);

        $measured = $report->fresh()->cellMeasurement();
        $this->assertSame([['cell' => 3, 'voltage' => 2.228, 'density' => null], ['cell' => 17, 'voltage' => 2.231, 'density' => 1.24]], $measured['replaced']);
        $this->assertCount(24, $measured['cells']);
        $this->assertSame(round(array_sum($full), 2), $measured['total_voltage'], 'Without a total the sum of the cells.');

        // In the PDF for review, and the rest of the report as usual.
        $fresh = $report->fresh();
        $html = view('interventions.report-pdf', ['report' => $fresh, 'project' => $fresh->project])->render();
        $this->assertStringContainsString(__('erp.interventions.cells.title'), $html);
        $this->assertStringContainsString('2,231 V', $html);
        $this->assertSame(['CELVERVANGING'], $report->categoryNames());
        $this->assertSame('XVB 3G2.5', $report->materials->sole()->description);
    }

    #[Test]
    public function an_intervention_past_its_due_date_and_not_executed_turns_red(): void
    {
        $this->travelTo('2026-11-24');
        $team = Team::factory()->create();
        $member = User::factory()->withPermissions(['projects' => ['view']])->inTeam($team)->create();
        $late = $this->intervention(['intervention_due_on' => '2026-11-20'], $team);
        $today = $this->intervention(['intervention_due_on' => '2026-11-24'], $team);
        $done = $this->intervention(['intervention_due_on' => '2026-11-20', 'status' => ProjectStatus::Executed], $team);
        $none = $this->intervention(['intervention_due_on' => null], $team);

        $this->assertSame([true, false, false, false], [$late->isInterventionOverdue(), $today->isInterventionOverdue(), $done->isInterventionOverdue(), $none->isInterventionOverdue()]);
        $this->assertSame([$late->id], Project::query()->interventionOverdue()->pluck('id')->all());

        $this->actingAs($member);
        Livewire::test(ListInterventions::class)
            ->set('activeTab', 'all')
            ->assertSeeHtml('erp-row-overdue')
            ->filterTable('overdue', true)
            ->assertCanSeeTableRecords([$late])
            ->assertCanNotSeeTableRecords([$today, $done, $none]);

        // Not executed yet: still red after a status change that is not "executed".
        $late->update(['status' => ProjectStatus::InProgress]);
        $this->assertTrue($late->fresh()->isInterventionOverdue());
    }
}
