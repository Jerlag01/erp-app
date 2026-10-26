<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PushMessages\Pages\ManagePushMessages;
use App\Models\PushMessage;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PushMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok'], ['status' => 'ok']]])]);
        $this->withoutDefer();
        Filament::setCurrentPanel('admin');
    }

    protected function sender(): User
    {
        return User::factory()->create(['role_id' => Role::factory()->create(['is_admin' => true, 'permissions' => ['push_messages' => ['send']]])->id]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function message(array $overrides = []): array
    {
        return [
            'audience' => 'all',
            'title' => ['nl' => 'Werfsluiting', 'fr' => 'Fermeture du chantier', 'en' => 'Site closed'],
            'body' => ['nl' => 'Morgen geen toegang.', 'fr' => 'Pas d\'accès demain.', 'en' => 'No access tomorrow.'],
            ...$overrides,
        ];
    }

    #[Test]
    public function only_administrators_with_the_ticked_permission_send_messages(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/push-messages')->assertForbidden();
        $this->actingAs(User::factory()->withPermissions(['push_messages' => ['send']])->create())->get('/admin/push-messages')->assertForbidden();
        $this->actingAs($this->sender())->get('/admin/push-messages')->assertOk();
    }

    #[Test]
    public function every_employee_gets_the_message_in_their_own_language_only(): void
    {
        $jan = User::factory()->create(['locale' => 'nl']);
        $marie = User::factory()->create(['locale' => 'fr']);
        User::factory()->create(['is_active' => false]);
        $janAuth = $this->mobileAuth($jan, 'ExponentPushToken[jan]');
        $this->mobileAuth($marie, 'ExponentPushToken[marie]');
        $this->actingAs($sender = $this->sender());

        Livewire::test(ManagePushMessages::class)
            ->callAction('create', $this->message())
            ->assertHasNoActionErrors();

        $message = PushMessage::sole();
        // The sender is an active employee too.
        $this->assertSame(3, $message->recipients_count);
        $this->assertSame($sender->id, $message->sent_by);

        Http::assertSent(fn (Request $request): bool => collect(json_decode($request->body(), true))->contains(fn (array $push): bool => $push['to'] === 'ExponentPushToken[jan]' && $push['title'] === 'Werfsluiting' && $push['data'] === ['screen' => 'messages', 'id' => $message->id]));
        Http::assertSent(fn (Request $request): bool => collect(json_decode($request->body(), true))->contains(fn (array $push): bool => $push['to'] === 'ExponentPushToken[marie]' && $push['title'] === 'Fermeture du chantier' && $push['body'] === 'Pas d\'accès demain.'));
        Http::assertNotSent(fn (Request $request): bool => collect(json_decode($request->body(), true))->contains(fn (array $push): bool => $push['to'] === 'ExponentPushToken[jan]' && $push['title'] !== 'Werfsluiting'));

        // In the app: the message in their language, unread until opened.
        $this->getJson('/api/v1/messages', $janAuth)->assertOk()
            ->assertJsonPath('data.0.title', 'Werfsluiting')
            ->assertJsonPath('data.0.read', false);
        $this->getJson('/api/v1/me', $janAuth)->assertJsonPath('data.features.unread_messages', 1);
        $this->postJson('/api/v1/messages/read', ['id' => $message->id], $janAuth)->assertOk()->assertJsonPath('unread', 0);
        $this->getJson('/api/v1/messages', $janAuth)->assertJsonPath('data.0.read', true);
    }

    #[Test]
    public function all_three_languages_are_needed_and_teams_or_people_can_be_chosen(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->inTeam($team)->create();
        User::factory()->create();
        $this->actingAs($this->sender());

        Livewire::test(ManagePushMessages::class)
            ->callAction('create', $this->message(['title' => ['nl' => 'Enkel NL', 'fr' => '', 'en' => '']]))
            ->assertHasActionErrors(['title.fr' => 'required', 'title.en' => 'required']);

        Livewire::test(ManagePushMessages::class)
            ->callAction('create', $this->message(['audience' => 'teams', 'team_ids' => [$team->id]]))
            ->assertHasNoActionErrors();

        $this->assertSame([$member->id], PushMessage::sole()->recipients()->pluck('users.id')->all());
    }
}
