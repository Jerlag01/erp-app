<?php

namespace Tests\Feature;

use App\Enums\IncidentType;
use App\Enums\SuggestionType;
use App\Models\Incident;
use App\Models\Location;
use App\Models\Suggestion;
use App\Models\User;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileIncidentAndSuggestionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $admin;

    /** @var array{Authorization: string} */
    protected array $auth;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->withPermissions([])->create(['name' => 'Jan Peeters', 'locale' => 'nl']);
        $this->auth = [...$this->mobileAuth($this->user), 'Accept' => 'application/json'];
    }

    #[Test]
    public function an_incident_is_reported_with_photos_from_the_app(): void
    {
        WorkSite::factory()->create(['cow_code' => '02GAM', 'city' => 'Gent']);
        $site = WorkSite::factory()->create(['cow_code' => '03WOM', 'city' => 'Wommelgem']);
        Location::factory()->create(['name' => 'Depot Luik']);

        $this->getJson('/api/v1/me', $this->auth)->assertJsonPath('data.features.incidents', true);
        $this->getJson('/api/v1/incidents', $this->auth)
            ->assertOk()
            ->assertJsonPath('data.types.0.value', 'accident')
            ->assertJsonPath('data.locations.0.label', 'Depot Luik')
            ->assertJsonPath('data.labels.description', 'Beschrijving');
        $this->getJson('/api/v1/incidents/work-sites?search=WOM', $this->auth)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $site->id);

        $this->post('/api/v1/incidents', [
            'type' => 'near_miss',
            'place_type' => 'work_site',
            'place_id' => $site->id,
            'location_details' => 'Naast de container',
            'material_damage' => '1',
            'material_damage_details' => 'Deur kast',
            'description' => 'Kabelgoot viel bijna naar beneden.',
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ], $this->auth)->assertCreated();

        $incident = Incident::query()->sole();
        $this->assertSame([IncidentType::NearMiss, $this->user->id, 'Jan Peeters'], [$incident->type, $incident->user_id, $incident->reporter_name]);
        $this->assertStringStartsWith('03WOM', $incident->place_label);
        $this->assertTrue($incident->material_damage);
        $this->assertCount(2, $incident->photos);
        Storage::disk('local')->assertExists($incident->photos[0]);
        $this->assertSame(1, $this->admin->notifications()->count());

        $this->getJson('/api/v1/incidents', $this->auth)->assertJsonPath('data.mine.0.id', $incident->id);

        $this->post('/api/v1/incidents', ['type' => 'accident', 'place_type' => 'work_site'], $this->auth)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['place_id', 'description']);
    }

    #[Test]
    public function an_idea_or_complaint_is_submitted_from_the_app(): void
    {
        $this->getJson('/api/v1/suggestions', $this->auth)->assertOk()->assertJsonPath('data.types.1.value', 'complaint');

        $this->post('/api/v1/suggestions', [
            'type' => 'idea',
            'description' => 'Een tweede laadpaal aan het magazijn.',
            'may_be_public' => '1',
            'photos' => [UploadedFile::fake()->image('plek.jpg')],
        ], $this->auth)->assertCreated();

        $suggestion = Suggestion::query()->sole();
        $this->assertSame([SuggestionType::Idea, 'Jan', 'Peeters', true], [$suggestion->type, $suggestion->first_name, $suggestion->last_name, $suggestion->may_be_public]);
        $this->assertCount(1, $suggestion->photos);
        $this->assertSame(1, $this->admin->notifications()->count());

        $suggestion->update(['response' => 'Staat op de planning.']);
        $this->getJson('/api/v1/suggestions', $this->auth)->assertJsonPath('data.mine.0.response', 'Staat op de planning.');
    }

    #[Test]
    public function the_modules_can_be_switched_off(): void
    {
        $this->setModules(['incidents' => false, 'suggestions' => false]);

        $this->getJson('/api/v1/me', $this->auth)->assertJsonPath('data.features.incidents', false)->assertJsonPath('data.features.suggestions', false);
        $this->getJson('/api/v1/incidents', $this->auth)->assertForbidden();
        $this->postJson('/api/v1/suggestions', ['type' => 'idea', 'description' => 'x'], $this->auth)->assertForbidden();
    }
}
