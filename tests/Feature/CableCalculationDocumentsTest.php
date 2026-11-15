<?php

namespace Tests\Feature;

use App\Enums\AsbuiltCategory;
use App\Enums\AsbuiltStatus;
use App\Enums\ProjectFileSection;
use App\Enums\ProjectStatus;
use App\Filament\Resources\CableCalculations\Pages\CreateCableCalculation;
use App\Filament\Resources\CableCalculations\Pages\EditCableCalculation;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\ExtraFilesRelationManager;
use App\Models\AsbuiltFile;
use App\Models\CableCalculation;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Team;
use App\Models\User;
use App\Support\CableCalculationDocuments;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The PDF of a project's cable calculation in the project's extra files and in the "cable dimensions" of its as-built.
 */
class CableCalculationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        Filament::setCurrentPanel('app');
        $this->team = Team::factory()->create();
    }

    protected function project(ProjectStatus $status = ProjectStatus::Executed): Project
    {
        $project = Project::factory()->inCategory('610')->create(['status' => ProjectStatus::Planned]);
        $project->teams()->attach($this->team);
        $project->update(['status' => $status]);

        return $project->refresh();
    }

    protected function user(): User
    {
        $user = User::factory()->withPermissions([
            'projects' => ['view', 'update', 'files'],
            'cable_calculations' => ['view', 'create', 'update', 'delete', 'generate_pdf'],
        ])->inTeam($this->team)->create();
        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function a_saved_calculation_of_a_project_is_kept_in_its_extra_files_and_as_built(): void
    {
        $user = $this->user();
        $project = $this->project();
        $asbuilt = $project->asbuilt;
        $calculation = CableCalculation::factory()->withCircuit()->create(['project_id' => $project->id, 'name' => 'Nota 02NO1']);

        Livewire::test(EditCableCalculation::class, ['record' => $calculation->getRouteKey()])->call('save')->assertHasNoFormErrors();

        $calculation->refresh();
        $file = $calculation->projectFile;
        $this->assertSame([$project->id, ProjectFileSection::Extra->value, 'kabelberekening-nota-02no1.pdf', 1], [$file->project_id, $file->section, $file->name, $file->version]);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($file->latestVersion->path));

        $inAsbuilt = $calculation->asbuiltFile;
        $this->assertSame([$asbuilt->id, AsbuiltCategory::CableDimensions->value, $user->id], [$inAsbuilt->asbuilt_id, $inAsbuilt->category, $inAsbuilt->uploaded_by]);
        $firstPath = $inAsbuilt->path;
        Storage::disk('local')->assertExists($firstPath);

        // Saving again: a new version in the extra files, the as-built's copy replaced.
        Livewire::test(EditCableCalculation::class, ['record' => $calculation->getRouteKey()])->call('save');
        $this->assertSame(2, $file->fresh()->version);
        $this->assertSame(1, AsbuiltFile::query()->where('category', 'cable_dimensions')->count());
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($inAsbuilt->fresh()->path);

        // Without a project: gone from the project.
        $calculation->update(['project_id' => null]);
        app(CableCalculationDocuments::class)->sync($calculation);
        $this->assertSame(0, ProjectFile::query()->count());
        $this->assertSame(0, AsbuiltFile::query()->count());
        $this->assertNull($calculation->fresh()->project_file_id);
    }

    #[Test]
    public function a_new_as_built_takes_the_calculations_and_a_closed_one_is_left_alone(): void
    {
        $this->user();
        $project = $this->project(ProjectStatus::Planned);
        $calculation = CableCalculation::factory()->withCircuit()->create(['project_id' => $project->id]);
        app(CableCalculationDocuments::class)->sync($calculation);
        $this->assertNotNull($calculation->fresh()->project_file_id);
        $this->assertNull($calculation->fresh()->asbuilt_file_id);

        // The as-built is made when the project is executed: the calculation joins its cable dimensions.
        $project->update(['status' => ProjectStatus::Executed]);
        $asbuilt = $project->refresh()->asbuilt;
        $this->assertSame([AsbuiltCategory::CableDimensions->value], $asbuilt->files()->pluck('category')->all());

        // Closed: its files (and ZIP) stay as they were; deleting the calculation keeps them there too.
        $asbuilt->update(['status' => AsbuiltStatus::Closed]);
        $path = $calculation->fresh()->asbuiltFile->path;
        app(CableCalculationDocuments::class)->sync($calculation->fresh());
        $this->assertSame($path, $calculation->fresh()->asbuiltFile->path);

        $calculation->fresh()->delete();
        $this->assertSame(0, ProjectFile::query()->count());
        $this->assertSame(1, AsbuiltFile::query()->count());
    }

    #[Test]
    public function a_calculation_is_made_from_the_extra_files_of_the_project(): void
    {
        $this->user();
        $project = $this->project();
        CableCalculation::factory()->withCircuit()->create(['project_id' => $project->id]);

        Livewire::test(ExtraFilesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => EditProject::class])
            ->assertActionVisible(TestAction::make('makeCableCalculation')->table())
            ->assertActionHasUrl(TestAction::make('makeCableCalculation')->table(), route('filament.app.resources.cable-calculations.create', ['project' => $project->id]))
            ->assertActionVisible(TestAction::make('projectCableCalculations')->table());

        Livewire::withQueryParams(['project' => $project->id])
            ->test(CreateCableCalculation::class)
            ->assertSet('data.project_id', $project->id);
    }
}
