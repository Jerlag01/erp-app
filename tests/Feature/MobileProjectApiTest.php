<?php

namespace Tests\Feature;

use App\Enums\ProjectFileSection;
use App\Enums\ProjectStatus;
use App\Models\GpwRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectPart;
use App\Models\StockItem;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileProjectApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function project(Team $team, string $category = '620', string $cowCode = '02GAM'): Project
    {
        $contact = WorkSiteContact::factory()->create(['first_name' => 'Jan', 'last_name' => 'Peeters', 'company' => null, 'phone' => '0470 12 34 56']);
        $site = WorkSite::factory()->create(['cow_code' => $cowCode, 'work_site_contact_id' => $contact->id]);
        $project = Project::factory()->inCategory($category)->for($site)->create([
            'short_description' => 'New distribution board',
            'status' => ProjectStatus::ToBeExecuted,
        ]);
        $project->teams()->attach($team);

        return $project;
    }

    #[Test]
    public function team_members_see_the_overview_gpw_and_files_of_their_projects_but_no_offers_or_plans(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->inTeam($team)->create();
        $leader = User::factory()->create(['name' => 'Lea Leader']);
        $auth = $this->mobileAuth($employee);
        $project = $this->project($team);
        $project->leaders()->attach($leader);
        GpwRequest::factory()->approved()->for($project)->create(['starts_on' => '2026-11-16', 'ends_on' => '2026-11-18']);

        $schematic = ProjectFile::store($project, ProjectFileSection::Schematics, UploadedFile::fake()->create('schema.pdf', 20, 'application/pdf'));
        ProjectFile::store($project, ProjectFileSection::Documents, UploadedFile::fake()->create('safety.docx', 10));
        ProjectFile::store($project, ProjectFileSection::Extra, UploadedFile::fake()->create('extra.xlsx', 10));
        $offer = ProjectFile::store($project, ProjectFileSection::Offers, UploadedFile::fake()->create('offer.pdf', 10, 'application/pdf'));
        ProjectFile::store($project, ProjectFileSection::Invoices, UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'));
        ProjectFile::store($project, ProjectFileSection::Plans, UploadedFile::fake()->create('plan.dwg', 10));

        // Not their team, not in the categories, or closed: not shown.
        $this->project(Team::factory()->create(), '620', '02AAA');
        $this->project($team, '604', '02BBB');
        $this->project($team, '620', '02CCC')->update(['status' => ProjectStatus::Closed]);

        $this->getJson('/api/v1/me', $auth)->assertJsonPath('data.features.projects', true);
        $this->getJson('/api/v1/projects', $auth)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $project->reference)
            ->assertJsonPath('data.0.gpw.approved', true)
            ->assertJsonPath('data.0.gpw.period', '16/11/2026 – 18/11/2026')
            ->assertJsonPath('data.0.gpw.days', 3);

        $response = $this->getJson("/api/v1/projects/{$project->id}", $auth)->assertOk()
            ->assertJsonPath('data.description', 'New distribution board')
            ->assertJsonPath('data.cow_code', '02GAM')
            ->assertJsonPath('data.contacts.0.name', 'Jan Peeters')
            ->assertJsonPath('data.contacts.0.phone', '0470 12 34 56')
            ->assertJsonPath('data.leaders.0.name', 'Lea Leader');

        $sections = collect($response->json('data.file_sections'))->pluck('section')->all();
        $this->assertSame(['documents', 'schematics', 'extra'], $sections);

        $this->postJson("/api/v1/projects/{$project->id}/files/{$offer->id}", [], $auth)->assertNotFound();
        $url = $this->postJson("/api/v1/projects/{$project->id}/files/{$schematic->id}", [], $auth)->assertOk()->json('url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('mobile.project-file', ['project' => $project->id, 'file' => $offer->id]))->assertForbidden();
    }

    #[Test]
    public function others_cannot_open_the_project(): void
    {
        $team = Team::factory()->create();
        $project = $this->project($team);
        $outsider = $this->mobileAuth(User::factory()->inTeam(Team::factory()->create())->create());

        $this->getJson('/api/v1/me', $outsider)->assertJsonPath('data.features.projects', false);
        $this->getJson("/api/v1/projects/{$project->id}", $outsider)->assertNotFound();
        $this->postJson("/api/v1/projects/{$project->id}/parts", ['description' => 'Cable', 'quantity' => 1], $outsider)->assertNotFound();
    }

    #[Test]
    public function team_members_add_used_items_from_stock_or_not_and_remove_only_their_own(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->inTeam($team)->create();
        $colleague = User::factory()->inTeam($team)->create();
        $auth = $this->mobileAuth($employee);
        $project = $this->project($team);
        $item = StockItem::factory()->create(['name' => 'Breaker 16A']);

        $this->postJson("/api/v1/projects/{$project->id}/parts", ['quantity' => 2], $auth)->assertUnprocessable()->assertJsonValidationErrors('description');

        $this->postJson("/api/v1/projects/{$project->id}/parts", ['stock_item_id' => $item->id, 'quantity' => 2], $auth)->assertCreated()
            ->assertJsonPath('data.parts.0.description', 'Breaker 16A')
            ->assertJsonPath('data.parts.0.in_stock', true);
        $this->postJson("/api/v1/projects/{$project->id}/parts", ['description' => 'Special gland M40', 'quantity' => 1, 'notes' => 'Bought on site'], $auth)->assertCreated()
            ->assertJsonPath('data.parts.1.in_stock', false)
            ->assertJsonPath('data.parts.1.can_remove', true);

        $theirs = ProjectPart::query()->create(['project_id' => $project->id, 'description' => 'Cable', 'quantity' => 10, 'added_by' => $colleague->id]);
        $this->deleteJson("/api/v1/projects/{$project->id}/parts/{$theirs->id}", [], $auth)->assertForbidden();

        $own = ProjectPart::query()->where('description', 'Special gland M40')->sole();
        $this->assertSame($employee->id, $own->added_by);
        $this->deleteJson("/api/v1/projects/{$project->id}/parts/{$own->id}", [], $auth)->assertOk();
        $this->assertSame(['Breaker 16A', 'Cable'], $project->parts()->pluck('description')->all());
    }
}
