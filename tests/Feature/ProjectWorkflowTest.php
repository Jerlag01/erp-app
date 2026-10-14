<?php

namespace Tests\Feature;

use App\Actions\Projects\CloseProject;
use App\Enums\ProjectFileSection;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Interventions\InterventionResource;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteArea;
use App\Support\ProjectProgress;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Filament::setCurrentPanel('app');
        $this->team = Team::factory()->create();
    }

    protected function employee(array $projects = ['view', 'create', 'update', 'files']): User
    {
        return User::factory()->withPermissions(['projects' => $projects])->inTeam($this->team)->create();
    }

    protected function project(string $category, array $attributes = []): Project
    {
        $project = Project::factory()->inCategory($category)->create($attributes);
        $project->teams()->attach($this->team);

        return $project;
    }

    #[Test]
    public function the_progress_of_a_610_project_completes_itself_and_invoicing_closes_it(): void
    {
        $this->actingAs($this->employee());
        $project = $this->project('610');
        $this->assertTrue(ProjectProgress::appliesTo($project));
        $this->assertFalse(ProjectProgress::appliesTo($this->project('607')));

        // A PO number completes "PO ontvangen".
        $project->poNumbers()->create(['number' => '4500012345']);
        $this->assertSame([4], $project->steps()->pluck('step')->all());

        // An offer completes the request, the site visit and the offer.
        ProjectFile::store($project, ProjectFileSection::Offers, UploadedFile::fake()->create('offerte.pdf', 20, 'application/pdf'));
        $this->assertEqualsCanonicalizing([1, 2, 3, 4], $project->steps()->pluck('step')->all());
        $this->assertNull($project->steps()->where('step', 1)->value('completed_on'), 'Date unknown.');

        // By hand, with today's date; ticking again unticks it.
        $page = Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertSee(__('erp.project_progress.steps.station_delivery'))
            ->call('toggleStep', 6);
        $this->assertSame(today()->toDateString(), $project->steps()->where('step', 6)->first()->completed_on->toDateString());
        $page->call('toggleStep', 6);
        $this->assertFalse($project->steps()->where('step', 6)->exists());
        $this->assertSame(29, ProjectProgress::percentage($project));

        // Invoiced: every step done, the project is closed and archived with its files.
        $project->update(['status' => ProjectStatus::Invoiced]);
        $project->refresh();

        $this->assertSame(14, $project->steps()->count());
        $this->assertSame(ProjectStatus::Closed, $project->status);
        $this->assertTrue($project->isArchived());
        Storage::disk('local')->assertExists($project->archive_path);

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($project->archive_path));
        $names = array_map(fn (int $index): string => $zip->getNameIndex($index), range(0, $zip->numFiles - 1));
        $zip->close();
        $this->assertCount(1, array_filter($names, fn (string $name): bool => str_ends_with($name, '/offerte.pdf')));

        // Archived: read-only for employees, only in the archive tab.
        $this->assertFalse(auth()->user()->can('update', $project));
        $this->get("/projects/{$project->id}/edit")->assertForbidden();
        Livewire::test(ListProjects::class)->assertCanNotSeeTableRecords([$project]);
        Livewire::test(ListProjects::class)->set('activeTab', 'archived')->assertCanSeeTableRecords([$project]);

        // The ZIP needs "projects.download_pdf".
        $this->get(route('projects.archive', $project))->assertForbidden();
        $this->actingAs($this->employee(['view', 'download_pdf']))->get(route('projects.archive', $project))->assertOk()->assertDownload();

        // Administrators reopen it.
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->callAction('reopen', ['status' => ProjectStatus::InvoicingToDo->value]);
        $this->assertFalse($project->fresh()->isArchived());
    }

    #[Test]
    public function any_project_is_archived_when_it_is_closed(): void
    {
        $project = $this->project('607');
        ProjectFile::store($project, ProjectFileSection::Documents, UploadedFile::fake()->create('meetstaat.xlsx', 10));

        $project->update(['status' => ProjectStatus::Invoiced]);
        $this->assertFalse($project->fresh()->isArchived(), 'Invoiced only closes 610–650 projects.');

        $project->update(['status' => ProjectStatus::Closed]);
        $this->assertTrue($project->fresh()->isArchived());
        Storage::disk('local')->assertExists($project->fresh()->archive_path);
        $this->assertSame($project->fresh()->archive_path, app(CloseProject::class)->zip($project->fresh()), 'Same name when built again.');
    }

    #[Test]
    public function a_project_is_copied_to_a_new_one_with_the_right(): void
    {
        $project = $this->project('607', ['short_description' => 'Laadpaal Gent']);
        $project->parts()->create(['description' => 'Kabel 5G6', 'quantity' => 25]);
        $project->poNumbers()->create(['number' => 'PO-1']);
        ProjectFile::store($project, ProjectFileSection::Plans, UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf'));
        $project->update(['status' => ProjectStatus::Closed]);

        $this->actingAs($this->employee());
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('copy');

        $this->actingAs($this->employee(['view', 'create', 'copy']));
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('copy', ['project_category_id' => $project->project_category_id, 'short_description' => 'Laadpaal Gent fase 2', 'with_files' => true])
            ->assertHasNoActionErrors();

        $copy = Project::query()->where('source_project_id', $project->id)->sole();
        $this->assertNotSame($project->number, $copy->number);
        $this->assertSame(['Laadpaal Gent fase 2', ProjectStatus::Offer, false], [$copy->short_description, $copy->status, $copy->isArchived()]);
        $this->assertSame(['Kabel 5G6'], $copy->parts()->pluck('description')->all());
        $this->assertSame(0, $copy->poNumbers()->count());
        $this->assertTrue($copy->teams()->whereKey($this->team->id)->exists());
        $this->assertSame(['plan.pdf'], $copy->files()->with('latestVersion')->get()->map(fn (ProjectFile $file) => $file->latestVersion->original_name)->all());
    }

    #[Test]
    public function category_608_1_follows_the_intervention_workflow(): void
    {
        $project = $this->project('608.1', ['work_order_number' => 'WO-608-1']);

        $this->assertTrue($project->isIntervention());
        $this->assertStringStartsWith('608.1-', $project->reference);

        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue(InterventionResource::getEloquentQuery()->whereKey($project->id)->exists());
        $this->get('/interventions')->assertOk()->assertSee('608.1');
    }

    #[Test]
    public function an_executed_604_in_flanders_gets_a_605(): void
    {
        $flanders = WorkSite::factory()->create(['cow_code' => '02GAM', 'work_site_area_id' => WorkSiteArea::query()->create(['name' => '1-VLA'])->id]);
        $wallonia = WorkSite::factory()->create(['cow_code' => '51NAM', 'work_site_area_id' => WorkSiteArea::query()->create(['name' => '4-WAL'])->id]);

        $project = $this->project('604', ['work_site_id' => $flanders->id]);
        $project->poNumbers()->create(['number' => '4500099999']);
        $project->update(['status' => ProjectStatus::Executed]);

        $followUp = Project::query()->where('source_project_id', $project->id)->sole();
        $this->assertSame(['605', '02GAM', 0], [$followUp->category->code, $followUp->cow_code, $followUp->poNumbers()->count()]);
        $this->assertTrue($followUp->teams()->whereKey($this->team->id)->exists());

        // Only once, also when the status changes again.
        $project->update(['status' => ProjectStatus::InvoicingToDo]);
        $this->assertSame(1, Project::query()->where('source_project_id', $project->id)->count());

        $walloon = $this->project('604', ['work_site_id' => $wallonia->id]);
        $walloon->update(['status' => ProjectStatus::Executed]);
        $this->assertSame(0, Project::query()->where('source_project_id', $walloon->id)->count());
    }

    #[Test]
    public function interim_employees_are_marked(): void
    {
        $employee = Employee::factory()->create(['is_interim' => true, 'interim_agency' => 'Randstad']);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/employees')->assertOk()->assertSee(__('erp.employees.interim'));
        $this->get("/admin/employees/{$employee->id}")->assertOk()->assertSee('Randstad');
    }

    #[Test]
    public function the_project_list_shows_the_progress_of_610_to_650_projects(): void
    {
        $this->actingAs($this->employee());
        $proximus = $this->project('620');
        $proximus->poNumbers()->create(['number' => 'PO-620']);
        $other = $this->project('607');

        Livewire::test(ListProjects::class)
            ->assertTableColumnVisible('progress')
            ->assertTableColumnStateSet('progress', ['done' => 1, 'total' => 14], $proximus)
            ->assertTableColumnStateSet('progress', null, $other)
            ->assertSee('1/14');

        Livewire::test(ListProjects::class)->set('activeTab', '620')->assertTableColumnVisible('progress');
        Livewire::test(ListProjects::class)->set('activeTab', '607')->assertTableColumnHidden('progress');
    }
}
