<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Settings;
use App\Models\BatteryDossier;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutlookCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDefer();
        Http::preventStrayRequests();
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'token-123', 'expires_in' => 3600]),
            'graph.microsoft.com/v1.0/users/*/events/*' => Http::response([], 200),
            'graph.microsoft.com/v1.0/users/*/events' => Http::response(['id' => 'event-1'], 201),
            'graph.microsoft.com/v1.0/users/*/calendar*' => Http::response(['id' => 'cal', 'name' => 'Calendar']),
        ]);
    }

    protected function enable(): void
    {
        settings()->save('planning', [...settings('planning'), 'outlook_enabled' => true, 'outlook_tenant_id' => 'tenant', 'outlook_client_id' => 'client', 'outlook_client_secret' => Crypt::encryptString('geheim')]);
    }

    #[Test]
    public function planning_items_are_created_changed_and_removed_in_the_employees_outlook_calendar(): void
    {
        $this->enable();
        $employee = User::factory()->create(['email' => 'jan@power.example']);
        $project = Project::factory()->inCategory('604')->create();
        BatteryDossier::factory()->create(['project_id' => $project->id, 'old_count' => 2, 'old_capacity_ah' => 500, 'old_technology' => 'OPzS', 'new_count' => 2, 'new_capacity_ah' => 490, 'new_technology' => 'OPzV']);

        $item = PlanningItem::factory()->forProject($project)->create(['user_id' => $employee->id, 'date' => '2026-11-05', 'start_time' => '07:30', 'end_time' => '16:00', 'notes' => 'Sleutel bij de buren']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/users/jan%40power.example/events')
            && $request->hasHeader('Authorization', 'Bearer token-123')
            && str_starts_with($request['subject'], $project->reference)
            && str_ends_with($request['subject'], ' · 2 x 500Ah OPzS -> 2 x 490Ah OPzV')
            // Always a full day, without the hours of the planning.
            && $request['isAllDay'] === true
            && $request['start'] === ['dateTime' => '2026-11-05T00:00:00', 'timeZone' => 'Romance Standard Time']
            && $request['end']['dateTime'] === '2026-11-06T00:00:00'
            && str_contains($request['body']['content'], 'Sleutel bij de buren'));
        $this->assertSame(['event-1', 'jan@power.example'], [$item->fresh()->outlook_event_id, $item->fresh()->outlook_mailbox]);

        // Moving the day changes the event.
        $item->refresh()->update(['date' => '2026-11-06']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH' && str_ends_with($request->url(), '/events/event-1') && $request['start']['dateTime'] === '2026-11-06T00:00:00');

        // Moved to a colleague: out of the old calendar, into the new one.
        $colleague = User::factory()->create(['email' => 'piet@power.example']);
        $item->refresh()->update(['user_id' => $colleague->id]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), '/users/jan%40power.example/events/event-1'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/users/piet%40power.example/events'));
        $this->assertSame('piet@power.example', $item->fresh()->outlook_mailbox);

        // Removed from the planning: removed from Outlook.
        $item->refresh()->delete();
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), '/users/piet%40power.example/events/event-1'));

        // One token for all of it.
        Http::assertSentCount(1 + 1 + 1 + 2 + 1);
    }

    #[Test]
    public function nothing_is_sent_while_the_link_is_switched_off(): void
    {
        PlanningItem::factory()->create();

        Http::assertNothingSent();
    }

    #[Test]
    public function administrators_set_up_and_test_the_link(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'chef@power.example']);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(Settings::class)
            ->fillForm(['planning.outlook_enabled' => true, 'planning.outlook_tenant_id' => 'tenant', 'planning.outlook_client_id' => 'client', 'planning.outlook_client_secret' => 'geheim'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(settings()->get('planning.outlook_enabled'));
        $this->assertNotSame('geheim', settings()->get('planning.outlook_client_secret'), 'Stored encrypted.');
        $this->assertSame('geheim', Crypt::decryptString(settings()->get('planning.outlook_client_secret')));

        // Saving again without a secret keeps it.
        Livewire::test(Settings::class)->call('save')->assertHasNoFormErrors();
        $this->assertSame('geheim', Crypt::decryptString(settings()->get('planning.outlook_client_secret')));

        Livewire::test(Settings::class)->callAction(TestAction::make('testOutlook')->schemaComponent('outlookActions', schema: 'form'))->assertNotified(__('erp.planning.outlook.test_ok'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'login.microsoftonline.com/tenant/oauth2/v2.0/token') && $request['client_secret'] === 'geheim');
    }
}
