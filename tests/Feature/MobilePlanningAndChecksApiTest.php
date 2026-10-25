<?php

namespace Tests\Feature;

use App\Actions\Planning\NotifyPlanningChange;
use App\Enums\AssetCheckResult;
use App\Enums\AssetProposalStatus;
use App\Filament\Resources\AssetChecks\Pages\ManageAssetChecks;
use App\Models\Absence;
use App\Models\Asset;
use App\Models\AssetCheck;
use App\Models\AssetProposal;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobilePlanningAndChecksApiTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected User $user;

    /** @var array{Authorization: string} */
    protected array $auth;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 09:00:00');
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $this->withoutDefer();
        Filament::setCurrentPanel('app');

        $this->team = Team::factory()->create(['name' => 'Mobiele ploeg test']);
        $this->user = User::factory()->withPermissions(['projects' => ['view']])->inTeam($this->team)->create(['name' => 'Jan', 'locale' => 'nl']);
        $this->auth = $this->mobileAuth($this->user, 'ExponentPushToken[jan]');
    }

    #[Test]
    public function the_employee_sees_their_planning_and_that_of_their_team_read_only(): void
    {
        $colleague = User::factory()->withPermissions([])->inTeam($this->team)->create(['name' => 'Piet']);
        $outsider = User::factory()->withPermissions([])->inTeam()->create();
        $project = Project::factory()->inCategory('604')->create();
        PlanningItem::factory()->forProject($project)->create(['user_id' => $this->user->id, 'date' => '2026-10-08', 'start_time' => '07:30', 'end_time' => '16:00', 'notes' => 'Sleutel bij de buren']);
        PlanningItem::factory()->forProject($project)->create(['user_id' => $colleague->id, 'date' => '2026-10-09']);
        Absence::factory()->for($this->user)->create(['start_date' => '2026-10-09', 'end_date' => '2026-10-09']);

        $this->getJson('/api/v1/planning/people', $this->auth)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Jan')
            ->assertJsonPath('data.0.me', true)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/planning?from=2026-10-05&until=2026-10-11', $this->auth)
            ->assertOk()
            ->assertJsonCount(7, 'data.days')
            ->assertJsonPath('data.days.3.items.0.start', '07:30')
            ->assertJsonPath('data.days.3.items.0.topic', $project->reference)
            ->assertJsonPath('data.days.3.items.0.notes', 'Sleutel bij de buren')
            ->assertJsonPath('data.days.4.blocked', 'Ziekte')
            ->assertJsonPath('data.days.5.weekend', true);

        $this->getJson("/api/v1/planning?from=2026-10-05&until=2026-10-11&user_id={$colleague->id}", $this->auth)
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Piet')
            ->assertJsonCount(1, 'data.days.4.items');

        $this->getJson("/api/v1/planning?from=2026-10-05&until=2026-10-11&user_id={$outsider->id}", $this->auth)->assertForbidden();
        $this->getJson('/api/v1/planning?from=2026-10-01&until=2026-12-01', $this->auth)->assertUnprocessable();
    }

    #[Test]
    public function a_change_in_the_planning_is_pushed_to_the_phone(): void
    {
        $item = PlanningItem::factory()->forProject()->create(['user_id' => $this->user->id, 'date' => '2026-10-08']);

        app(NotifyPlanningChange::class)->handle($this->user, NotifyPlanningChange::CREATED, [$item], User::factory()->admin()->create(), false);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://exp.host/--/api/v2/push/send'
            && json_decode($request->body(), true)[0]['to'] === 'ExponentPushToken[jan]'
            && json_decode($request->body(), true)[0]['data'] === ['screen' => 'planning', 'date' => '2026-10-08']);

        // Planning yourself on the platform is pushed to your phone too (without a notification in the platform).
        $before = $this->user->notifications()->count();
        app(NotifyPlanningChange::class)->handle($this->user, NotifyPlanningChange::UPDATED, [$item], $this->user, false);
        Http::assertSentCount(2);
        $this->assertSame($before, $this->user->notifications()->count());
    }

    #[Test]
    public function the_app_sends_itself_a_test_notification_and_hears_why_it_failed(): void
    {
        // The app's other requests do not use up the test's limit.
        foreach (range(1, 10) as $request) {
            $this->getJson('/api/v1/me', $this->auth)->assertOk();
        }

        $this->postJson('/api/v1/push-test', [], $this->auth)
            ->assertOk()
            ->assertJsonPath('data.registered', true)
            ->assertJsonPath('data.sent', true);
        Http::assertSent(fn (Request $request): bool => json_decode($request->body(), true)[0]['to'] === 'ExponentPushToken[jan]');

        // Expo refuses (no Firebase key in EAS): the app is told, and the server logs it.
        Log::spy();
        Http::swap(new Factory);
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'error', 'message' => 'Unable to retrieve the FCM server key', 'details' => ['error' => 'InvalidCredentials']]]])]);
        $this->postJson('/api/v1/push-test', [], $this->auth)
            ->assertOk()
            ->assertJsonPath('data.sent', false)
            ->assertJsonPath('data.error', 'InvalidCredentials')
            ->assertJsonPath('data.detail', 'Unable to retrieve the FCM server key')
            ->assertJsonPath('data.message', __('erp.mobile.push_test.credentials', [], 'nl')."\n\nExpo: Unable to retrieve the FCM server key");
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Push notification refused by Expo' && $context['error'] === 'InvalidCredentials');

        // A phone without a token is told to allow notifications.
        $this->putJson('/api/v1/push-token', ['token' => null], $this->auth)->assertOk();
        $this->postJson('/api/v1/push-test', [], $this->auth)->assertOk()->assertJsonPath('data.registered', false);
    }

    #[Test]
    public function the_yearly_equipment_control_is_filled_in_from_the_app(): void
    {
        $drill = Asset::factory()->create(['team_id' => $this->team->id, 'inspection_date' => '2025-01-10', 'serial_number' => null]);

        $this->getJson('/api/v1/me', $this->auth)->assertJsonPath('data.features.open_asset_checks', 0);

        // Publishing the list pushes a notification to the team.
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ManageAssetChecks::class)->callAction('publish', ['teams' => [$this->team->id], 'year' => 2026])->assertHasNoActionErrors();
        $check = AssetCheck::query()->sole();
        Http::assertSent(fn (Request $request): bool => json_decode($request->body(), true)[0]['to'] === 'ExponentPushToken[jan]'
            && json_decode($request->body(), true)[0]['data']['screen'] === 'asset_check');

        $this->getJson('/api/v1/me', $this->auth)->assertJsonPath('data.features.open_asset_checks', 1);
        $this->getJson('/api/v1/asset-checks', $this->auth)->assertOk()->assertJsonPath('data.0.team', 'Mobiele ploeg test')->assertJsonPath('data.0.total', 1);

        $item = $check->items()->sole();
        $this->getJson("/api/v1/asset-checks/{$check->id}", $this->auth)
            ->assertOk()
            ->assertJsonPath('data.items.0.can_complete_serial_number', true);

        $this->putJson("/api/v1/asset-checks/{$check->id}/items/{$item->id}", ['result' => 'ok', 'serial_number' => 'SN-42', 'remark' => 'Nieuwe batterij'], $this->auth)
            ->assertOk()
            ->assertJsonPath('data.result', 'ok')
            ->assertJsonPath('data.checked_by', 'Jan');

        $this->assertSame(AssetCheckResult::Ok, $item->fresh()->result);
        $this->assertSame('2026-10-07', $drill->fresh()->inspection_date->toDateString());
        $this->assertSame('SN-42', $drill->fresh()->serial_number);

        $this->putJson("/api/v1/asset-checks/{$check->id}/remarks", ['remarks' => 'Ladder ontbreekt'], $this->auth)->assertOk();
        $this->assertSame('Ladder ontbreekt', $check->fresh()->remarks);

        $this->postJson("/api/v1/asset-checks/{$check->id}/proposals", ['name' => 'Ladder 3m', 'result' => 'ok'], $this->auth)
            ->assertCreated()
            ->assertJsonPath('data.proposals.0.name', 'Ladder 3m');
        $this->assertSame(AssetProposalStatus::Pending, AssetProposal::query()->sole()->status);

        // Another team, or a closed list, cannot be filled in.
        $other = $this->mobileAuth(User::factory()->withPermissions([])->inTeam()->create());
        $this->getJson("/api/v1/asset-checks/{$check->id}", $other)->assertForbidden();
        $check->update(['closed_at' => now()]);
        $this->putJson("/api/v1/asset-checks/{$check->id}/items/{$item->id}", ['result' => 'not_ok'], $this->auth)->assertForbidden();
    }
}
