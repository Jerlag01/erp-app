<?php

namespace Tests\Feature;

use App\Enums\ProjectFileSection;
use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\ProjectCategories\Pages\ManageProjectCategories;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\ExtraFilesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\OffersRelationManager;
use App\Filament\Resources\Projects\RelationManagers\PartsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\PlansRelationManager;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectFile;
use App\Models\StockItem;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Full project rights for a member of the given team.
     */
    protected function employee(?Team $team = null, array $extra = []): User
    {
        return User::factory()
            ->withPermissions(['projects' => ['view', 'create', 'update', 'delete', 'files', 'parts'], ...$extra])
            ->inTeam($team)
            ->create();
    }

    #[Test]
    public function the_ten_main_categories_exist_with_their_numbering(): void
    {
        $this->assertSame(['604', '605', '606', '607', '608', '610', '620', '630', '640', '650'], ProjectCategory::query()->orderBy('code')->pluck('code')->all());
        $this->assertTrue(ProjectCategory::where('code', '607')->value('has_short_description'));
        $this->assertFalse(ProjectCategory::where('code', '604')->value('has_short_description'));
    }

    #[Test]
    public function references_follow_the_numbering_of_the_category(): void
    {
        $site = WorkSite::factory()->create(['cow_code' => '02GAM']);
        $yy = now()->format('y');

        $this->assertSame('604-001-02GAM', Project::factory()->inCategory('604')->for($site)->create()->reference);
        $this->assertSame('604-002-02GAM', Project::factory()->inCategory('604')->for($site)->create()->reference);
        $this->assertSame("605-{$yy}001-02GAM", Project::factory()->inCategory('605')->for($site)->create()->reference);
        $this->assertSame("605-{$yy}002-02GAM", Project::factory()->inCategory('605')->for($site)->create()->reference);

        $private = Project::factory()->inCategory('606')->create(['client_name' => 'Familie Peeters']);
        $this->assertSame('606-001', $private->reference);
        $this->assertNull($private->work_site_id, 'Private projects have no work site.');

        $withDescription = Project::factory()->inCategory('607')->for($site)->create(['short_description' => 'Nieuwe verlichting']);
        $this->assertSame('607-001-02GAM', $withDescription->reference);
        $this->assertSame('Nieuwe verlichting', $withDescription->short_description);
        $this->assertNull(Project::factory()->inCategory('604')->for($site)->create(['short_description' => 'x'])->short_description);
    }

    #[Test]
    public function an_employee_creates_a_project_with_several_po_numbers(): void
    {
        Repeater::fake();
        $employee = $this->employee();
        $site = WorkSite::factory()->create(['cow_code' => '11ABC']);
        $this->actingAs($employee);
        Filament::setCurrentPanel('app');

        Livewire::test(CreateProject::class)
            ->fillForm([
                'project_category_id' => ProjectCategory::where('code', '608')->value('id'),
                'work_site_id' => $site->id,
                'short_description' => 'Laadpalen parking',
                'status' => ProjectStatus::Planned->value,
                'poNumbers' => [['number' => '4500123456'], ['number' => '4500123457']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::sole();
        $this->assertSame('608-001-11ABC', $project->reference);
        $this->assertSame(['4500123456', '4500123457'], $project->poNumbers->pluck('number')->all());
        $this->assertTrue($project->isLedBy($employee), 'The creator becomes project leader by default.');
        $this->assertTrue($project->creator->is($employee));
    }

    #[Test]
    public function po_numbers_have_ten_digits_and_a_work_site_is_required(): void
    {
        Repeater::fake();
        $this->actingAs($this->employee());
        Filament::setCurrentPanel('app');

        Livewire::test(CreateProject::class)
            ->fillForm([
                'project_category_id' => ProjectCategory::where('code', '604')->value('id'),
                'poNumbers' => [['number' => '123456789']],
            ])
            ->call('create')
            ->assertHasFormErrors(['work_site_id' => 'required', 'poNumbers.0.number' => 'size']);

        $this->assertSame(0, Project::count());
    }

    #[Test]
    public function private_projects_keep_client_details_instead_of_a_work_site(): void
    {
        $this->actingAs($this->employee());
        Filament::setCurrentPanel('app');

        Livewire::test(CreateProject::class)
            ->fillForm(['project_category_id' => ProjectCategory::where('code', '606')->value('id')])
            ->assertFormFieldHidden('work_site_id')
            ->assertFormFieldHidden('poNumbers')
            ->fillForm([
                'client_name' => 'Jan Peeters',
                'client_phone' => '0470 12 34 56',
                'street' => 'Kerkstraat',
                'house_number' => '5',
                'postal_code' => '2000',
                'city' => 'Antwerpen',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::sole();
        $this->assertSame('606-001', $project->reference);
        $this->assertSame('Jan Peeters', $project->client_name);
        $this->assertSame('Antwerpen', $project->city);
    }

    #[Test]
    public function only_project_leaders_and_assigned_teams_see_a_project(): void
    {
        $team = Team::factory()->create();
        $member = $this->employee($team);
        $outsider = $this->employee();
        $leader = $this->employee();
        $project = Project::factory()->create();
        $project->teams()->attach($team);
        $project->leaders()->attach($leader);
        $url = "/projects/{$project->id}";

        $this->actingAs($member)->get($url)->assertOk()->assertSee($project->reference);
        $this->actingAs($leader)->get($url)->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get("/admin/projects/{$project->id}")->assertOk();
        $this->actingAs($outsider)->get($url)->assertNotFound();

        $this->actingAs($outsider);
        Filament::setCurrentPanel('app');
        Livewire::test(ListProjects::class)->assertCanNotSeeTableRecords([$project]);

        $this->actingAs($member);
        Livewire::test(ListProjects::class)->assertCanSeeTableRecords([$project]);
    }

    #[Test]
    public function teams_do_not_see_offers_and_invoices_but_project_leaders_do(): void
    {
        Storage::fake('local');
        $team = Team::factory()->create();
        $member = $this->employee($team);
        $leader = $this->employee();
        $project = Project::factory()->create();
        $project->teams()->attach($team);
        $project->leaders()->attach($leader);
        $offer = ProjectFile::store($project, ProjectFileSection::Offers, UploadedFile::fake()->create('offerte.pdf', 20, 'application/pdf'));
        $url = route('projects.file', ['project' => $project, 'file' => $offer]);

        $this->actingAs($member);
        $this->assertFalse(OffersRelationManager::canViewForRecord($project, ViewProject::class));
        $this->assertTrue(DocumentsRelationManager::canViewForRecord($project, ViewProject::class));
        $this->get($url)->assertForbidden();

        $this->actingAs($leader);
        $this->assertTrue(OffersRelationManager::canViewForRecord($project, ViewProject::class));
        $this->get($url)->assertOk()->assertDownload('offerte.pdf');
    }

    #[Test]
    public function files_are_uploaded_replaced_by_new_versions_and_removed(): void
    {
        Storage::fake('local');
        $team = Team::factory()->create();
        $member = $this->employee($team);
        $project = Project::factory()->create();
        $project->teams()->attach($team);
        $this->actingAs($member);
        Filament::setCurrentPanel('app');

        $documents = Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->callAction(TestAction::make('upload')->table(), ['files' => [
                UploadedFile::fake()->create('meetstaat.xlsx', 30),
                UploadedFile::fake()->create('verslag.docx', 30),
            ]])
            ->assertHasNoFormErrors();

        $this->assertSame(['meetstaat.xlsx', 'verslag.docx'], $project->documents()->orderBy('name')->pluck('name')->all());
        $file = $project->documents()->where('name', 'meetstaat.xlsx')->sole();

        $documents->callAction(TestAction::make('newVersion')->table($file), ['file' => UploadedFile::fake()->create('meetstaat v2.xlsx', 40)])
            ->assertHasNoFormErrors();

        $file->refresh();
        $this->assertSame(2, $file->version);
        $this->assertSame('meetstaat v2.xlsx', $file->name);
        $this->assertCount(2, $file->versions);
        $this->get(route('projects.file', ['project' => $project, 'file' => $file, 'version' => 1]))->assertOk()->assertDownload('meetstaat.xlsx');
        $this->get(route('projects.file', ['project' => $project, 'file' => $file]))->assertOk()->assertDownload('meetstaat v2.xlsx');

        $paths = $file->versions->pluck('path')->all();
        $documents->callAction(TestAction::make(DeleteAction::class)->table($file));

        $this->assertModelMissing($file);
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    #[Test]
    public function each_zone_only_accepts_its_file_types(): void
    {
        Storage::fake('local');
        $leader = $this->employee();
        $project = Project::factory()->create();
        $project->leaders()->attach($leader);
        $this->actingAs($leader);
        Filament::setCurrentPanel('app');

        $upload = fn (string $manager, string $name) => Livewire::test($manager, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->callAction(TestAction::make('upload')->table(), ['files' => [UploadedFile::fake()->create($name, 10)]]);

        $upload(PlansRelationManager::class, 'gelijkvloers.pdf')->assertHasFormErrors(['files']);
        $upload(PlansRelationManager::class, 'gelijkvloers.dwg')->assertHasNoFormErrors();
        $upload(ExtraFilesRelationManager::class, 'setup.exe')->assertHasFormErrors(['files']);
        $upload(ExtraFilesRelationManager::class, 'Setup.MSI')->assertHasFormErrors(['files']);
        $upload(ExtraFilesRelationManager::class, 'export.zip')->assertHasNoFormErrors();

        $this->assertSame(['gelijkvloers.dwg'], $project->plans()->pluck('name')->all());
        $this->assertSame(['export.zip'], $project->extraFiles()->pluck('name')->all());
    }

    #[Test]
    public function the_part_list_uses_items_from_the_stock_list(): void
    {
        $leader = $this->employee();
        $project = Project::factory()->create();
        $project->leaders()->attach($leader);
        $cable = StockItem::factory()->inMeters()->create(['name' => 'XVB 3G2,5 kabel']);
        $this->actingAs($leader);
        Filament::setCurrentPanel('app');

        $this->assertArrayHasKey($cable->id, PartsRelationManager::searchItems('XVB'));

        Livewire::test(PartsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->callAction(TestAction::make('create')->table(), ['stock_item_id' => $cable->id, 'quantity' => 150.5, 'unit_id' => $cable->unit_id])
            ->assertHasNoFormErrors();

        $part = $project->parts()->sole();
        $this->assertSame('XVB 3G2,5 kabel', $part->description);
        $this->assertSame('150.500', $part->quantity);
        $this->assertSame((float) $cable->levels()->sum('quantity'), (float) $cable->fresh()->levels()->sum('quantity'), 'Stock is not touched.');
    }

    #[Test]
    public function a_category_with_projects_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $used = ProjectCategory::where('code', '604')->sole();
        Project::factory()->inCategory('604')->create();
        $empty = ProjectCategory::factory()->create(['code' => '660']);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        $this->assertFalse($admin->can('delete', $used));

        Livewire::test(ManageProjectCategories::class)
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($used))
            ->callAction(TestAction::make(DeleteAction::class)->table($empty));

        $this->assertModelExists($used);
        $this->assertModelMissing($empty);
    }

    #[Test]
    public function the_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['short_description' => null]);
        $project->poNumbers()->create(['number' => '4500999999']);

        $this->actingAs($admin)->get('/projects')->assertOk()->assertSee('4500999999');
        $this->actingAs($admin)->get('/projects/create')->assertOk();
        $this->actingAs($admin)->get("/projects/{$project->id}/edit")->assertOk();
        $this->actingAs($admin)->get('/admin/project-categories')->assertOk()->assertSee('605');
        $this->actingAs($admin)->get('/admin/roles/create')->assertOk()->assertSee(__('erp.permissions.areas.project_finance'));
    }

    #[Test]
    public function the_module_can_be_switched_off(): void
    {
        $team = Team::factory()->create();
        $member = $this->employee($team);
        $project = Project::factory()->create();
        $project->teams()->attach($team);
        $this->setModules(['projects' => false]);

        $this->actingAs($member)->get('/projects')->assertForbidden();
        $this->actingAs($member)->get("/projects/{$project->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/project-categories')->assertForbidden();
    }
}
