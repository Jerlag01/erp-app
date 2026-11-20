<?php

namespace Tests\Feature;

use App\Enums\ProjectFileSection;
use App\Filament\Resources\BtvControls\Pages\ManageBtvControls;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\BtvControlFilesRelationManager;
use App\Models\BtvControl;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BtvControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Filament::setCurrentPanel('app');
    }

    #[Test]
    public function a_btv_control_is_requested_confirmed_and_its_report_goes_into_the_project_files(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $project = Project::factory()->create();

        Livewire::test(ManageBtvControls::class)
            ->callAction('create', ['project_id' => $project->id, 'requested_on' => '2026-11-02', 'notes' => 'Via mail aan BTV'])
            ->assertHasNoActionErrors();

        $control = BtvControl::query()->sole();
        $this->assertSame(BtvControl::REQUESTED, $control->status());
        $this->assertSame($admin->id, $control->created_by);

        Livewire::test(ManageBtvControls::class)
            ->assertCanSeeTableRecords([$control])
            ->callTableAction('edit', $control, ['project_id' => $project->id, 'requested_on' => '2026-11-02', 'confirmed_on' => '2026-11-20'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(BtvControl::CONFIRMED, $control->fresh()->status());

        Livewire::test(ManageBtvControls::class)
            ->callTableAction('uploadReport', $control, ['report_upload' => UploadedFile::fake()->create('verslag-btv.pdf', 30, 'application/pdf')])
            ->assertHasNoTableActionErrors();

        $control->refresh();
        $this->assertSame(BtvControl::REPORTED, $control->status());
        $file = $control->report;
        $this->assertSame(ProjectFileSection::BtvControl->value, $file->section);
        $this->assertSame('verslag-btv.pdf', $file->name);
        $this->assertTrue($file->project->is($project));
        Storage::disk('local')->assertExists($file->latestVersion->path);
        $this->assertStringStartsWith("projects/{$project->id}/btv_control/", $file->latestVersion->path);

        // A new report becomes the next version of the same file.
        Livewire::test(ManageBtvControls::class)
            ->set('activeTab', 'reported')
            ->callTableAction('edit', $control, ['project_id' => $project->id, 'requested_on' => '2026-11-02', 'confirmed_on' => '2026-11-20', 'report_upload' => UploadedFile::fake()->create('verslag-v2.pdf', 30, 'application/pdf')])
            ->assertHasNoTableActionErrors();
        $this->assertSame(2, $file->fresh()->version);
        $this->assertSame(1, $project->btvControlFiles()->count());

        // The project shows the "BTV control" files.
        Livewire::test(BtvControlFilesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$file]);
        $this->get(route('projects.file', ['project' => $project, 'file' => $file]))->assertOk();
    }

    #[Test]
    public function only_pdf_or_photos_are_accepted_as_report(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $control = BtvControl::factory()->create();

        Livewire::test(ManageBtvControls::class)
            ->callTableAction('uploadReport', $control, ['report_upload' => UploadedFile::fake()->create('script.exe', 10)])
            ->assertHasTableActionErrors(['report_upload']);
        $this->assertNull($control->fresh()->project_file_id);
    }

    #[Test]
    public function the_overview_follows_the_permission_and_the_projects_the_user_can_see(): void
    {
        $team = Team::factory()->create();
        $mine = Project::factory()->create();
        $mine->teams()->attach($team);
        $visible = BtvControl::factory()->for($mine)->create();
        $hidden = BtvControl::factory()->create();

        $viewer = User::factory()->withPermissions(['btv_controls' => ['view'], 'projects' => ['view']])->inTeam($team)->create();
        $this->actingAs($viewer);

        Livewire::test(ManageBtvControls::class)
            ->assertCanSeeTableRecords([$visible])
            ->assertCanNotSeeTableRecords([$hidden])
            ->assertActionHidden('create')
            ->assertTableActionHidden('uploadReport', $visible);

        $this->actingAs(User::factory()->withPermissions(['projects' => ['view']])->inTeam()->create());
        $this->get(ManageBtvControls::getUrl(panel: 'app'))->assertForbidden();
    }
}
