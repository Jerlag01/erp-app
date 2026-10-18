<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Team;
use App\Models\ToolboxMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileToolboxApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    /** @var array{Authorization: string} */
    protected array $auth;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->user = User::factory()->withPermissions([])->inTeam(Team::factory()->create())->create(['name' => 'Kevin Rouzée', 'locale' => 'nl']);
        Employee::factory()->for($this->user)->create();
        $this->auth = $this->mobileAuth($this->user);
    }

    protected function meeting(): ToolboxMeeting
    {
        Storage::disk('local')->put('toolbox/files/heffen.pdf', '%PDF-1.4 test');

        return ToolboxMeeting::factory()->published()->create([
            'attachments' => ['toolbox/files/heffen.pdf'],
            'attachment_names' => ['toolbox/files/heffen.pdf' => 'Heffen en tillen.pdf'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function signature(): array
    {
        return ['name' => 'Kevin Rouzée', 'width' => 300, 'height' => 120, 'strokes' => [[[10, 40], [60, 20], [120, 60]], [[20, 80], [200, 80]]]];
    }

    #[Test]
    public function the_employee_reads_and_signs_a_toolbox_meeting_from_the_app(): void
    {
        $meeting = $this->meeting();
        ToolboxMeeting::factory()->create(['title' => 'Concept']);

        $this->getJson('/api/v1/me', $this->auth)
            ->assertJsonPath('data.features.toolbox', true)
            ->assertJsonPath('data.features.open_toolbox', 1);

        $this->getJson('/api/v1/toolbox', $this->auth)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.month', 'September 2026')
            ->assertJsonPath('data.0.signed', false);

        $this->getJson("/api/v1/toolbox/{$meeting->id}", $this->auth)
            ->assertOk()
            ->assertJsonPath('data.month_fr', 'Septembre 2026')
            ->assertJsonPath('data.instructors', 'Rudy Cools & Alain Dubois')
            ->assertJsonPath('data.attachments.0.name', 'Heffen en tillen.pdf')
            ->assertJsonPath('data.can_sign', false);
        $this->assertStringContainsString('Veilig tillen', $this->getJson("/api/v1/toolbox/{$meeting->id}", $this->auth)->json('data.body_html'));

        // Not everything read yet: signing is refused.
        $this->postJson("/api/v1/toolbox/{$meeting->id}/sign", $this->signature(), $this->auth)->assertUnprocessable()->assertJsonPath('code', 'read_first');

        $this->postJson("/api/v1/toolbox/{$meeting->id}/read", [], $this->auth)->assertOk()->assertJsonPath('data.text_read', true)->assertJsonPath('data.can_sign', false);

        $link = $this->postJson("/api/v1/toolbox/{$meeting->id}/attachments/0", [], $this->auth)
            ->assertOk()
            ->assertJsonPath('data.attachments.0.opened', true)
            ->assertJsonPath('data.can_sign', true)
            ->json('url');

        // The link works without a session, and not when tampered with.
        $this->get($link)->assertOk()->assertDownload('Heffen en tillen.pdf');
        $this->get($link.'x')->assertForbidden();

        $this->postJson("/api/v1/toolbox/{$meeting->id}/sign", $this->signature(), $this->auth)
            ->assertOk()
            ->assertJsonPath('data.signed', true)
            ->assertJsonPath('data.signer_name', 'Kevin Rouzée');

        $participant = $meeting->participants()->sole();
        Storage::disk('local')->assertExists($participant->signature_path);
        $this->assertStringStartsWith("\x89PNG", Storage::disk('local')->get($participant->signature_path));

        $this->postJson("/api/v1/toolbox/{$meeting->id}/sign", $this->signature(), $this->auth)->assertStatus(409);
        $this->getJson('/api/v1/me', $this->auth)->assertJsonPath('data.features.open_toolbox', 0);
    }

    #[Test]
    public function drafts_and_a_switched_off_module_are_not_shown(): void
    {
        $draft = ToolboxMeeting::factory()->create();
        $this->getJson("/api/v1/toolbox/{$draft->id}", $this->auth)->assertNotFound();

        $this->setModules(['toolbox' => false]);
        $this->getJson('/api/v1/toolbox', $this->auth)->assertForbidden();
        $this->getJson('/api/v1/me', $this->auth)->assertJsonPath('data.features.toolbox', false);
    }
}
