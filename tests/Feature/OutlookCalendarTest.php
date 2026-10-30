<?php

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Filament\Admin\Pages\Settings;
use App\Models\Absence;
use App\Models\BatteryDossier;
use App\Models\Holiday;
use App\Models\OutlookEvent;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\Training;
use App\Models\User;
use App\Support\OutlookCalendar;
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
            // 604: the batteries instead of the project description.
            && $request['subject'] === $project->reference.' · 2 x 500Ah OPzS -> 2 x 490Ah OPzV'
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

    #[Test]
    public function the_events_follow_the_batteries_filled_in_after_planning(): void
    {
        $this->enable();
        $employee = User::factory()->create(['email' => 'jan@power.example']);
        $project = Project::factory()->inCategory('604')->create(['short_description' => 'Vervanging batterijen']);
        $item = PlanningItem::factory()->forProject($project)->create(['user_id' => $employee->id, 'date' => today()->addDays(3)->toDateString()]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && ($request['subject'] ?? null) === $project->reference);
        $this->assertNotNull($item->fresh()->outlook_event_id);

        // The dossier is filled in later: the planned event gets the batteries.
        $dossier = BatteryDossier::factory()->create(['project_id' => $project->id, 'old_count' => 1, 'old_capacity_ah' => 300, 'old_technology' => 'OPzS', 'new_count' => 2, 'new_capacity_ah' => 490, 'new_technology' => 'OPzV']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH' && ($request['subject'] ?? null) === $project->reference.' · 1 x 300Ah OPzS -> 2 x 490Ah OPzV');

        $dossier->update(['new_count' => 3]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH' && ($request['subject'] ?? null) === $project->reference.' · 1 x 300Ah OPzS -> 3 x 490Ah OPzV');
    }

    #[Test]
    public function absences_are_out_of_office_in_the_employees_calendar_with_private_reasons_marked_private(): void
    {
        $this->enable();
        $employee = User::factory()->create(['email' => 'jan@power.example', 'locale' => 'nl']);

        $sick = Absence::factory()->for($employee)->create(['start_date' => '2026-11-09', 'end_date' => '2026-11-11']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/events')
            && str_ends_with($request->url(), '/users/jan%40power.example/events')
            && $request['subject'] === 'Ziekte'
            && $request['isAllDay'] === true
            && $request['showAs'] === 'oof'
            && $request['sensitivity'] === 'private'
            && $request['start']['dateTime'] === '2026-11-09T00:00:00'
            && $request['end']['dateTime'] === '2026-11-12T00:00:00');
        $this->assertSame('event-1', OutlookEvent::query()->whereMorphedTo('source', $sick)->sole()->event_id);

        $sick->refresh()->update(['end_date' => '2026-11-13']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH' && str_ends_with($request->url(), '/events/event-1') && $request['end']['dateTime'] === '2026-11-14T00:00:00');

        $sick->refresh()->delete();
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), '/users/jan%40power.example/events/event-1'));
        $this->assertSame(0, OutlookEvent::query()->count());

        // Overtime as leave is not private, half a day is in the title.
        Absence::factory()->for($employee)->ofType(AbsenceType::Overtime)->create(['start_date' => '2026-11-20', 'end_date' => '2026-11-20', 'half_day' => true]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/events') && $request['subject'] === 'Recup (halve dag)' && $request['sensitivity'] === 'normal');
    }

    #[Test]
    public function holidays_go_to_every_active_employee_and_trainings_to_their_participants(): void
    {
        $this->enable();
        $jan = User::factory()->create(['email' => 'jan@power.example']);
        $piet = User::factory()->create(['email' => 'piet@power.example']);
        User::factory()->create(['email' => 'oud@power.example', 'is_active' => false]);

        $holiday = Holiday::factory()->create(['date' => '2026-12-25', 'name' => 'Kerstmis', 'type' => Holiday::HOLIDAY]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/events') && str_contains($request->url(), 'jan%40power.example') && $request['subject'] === 'Kerstmis');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/events') && str_contains($request->url(), 'piet%40power.example'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'oud%40power.example'));
        $this->assertSame(2, OutlookEvent::query()->whereMorphedTo('source', $holiday)->count());

        Holiday::factory()->create(['date' => '2026-12-28', 'name' => 'Brugdag', 'type' => Holiday::RECUPERATION]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/events') && $request['subject'] === 'IR · Brugdag');

        $training = Training::factory()->create(['name' => 'VCA', 'start_date' => '2027-01-11', 'end_date' => '2027-01-12', 'city' => 'Gent']);
        $training->participants()->sync([$jan->id, $piet->id]);
        app(OutlookCalendar::class)->syncEntry($training);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/events')
            && str_contains($request->url(), 'jan%40power.example')
            && $request['subject'] === 'Opleiding: VCA'
            && $request['showAs'] === 'busy'
            && str_contains($request['location']['displayName'], 'Gent')
            && $request['end']['dateTime'] === '2027-01-13T00:00:00');

        // Piet no longer takes part: out of his calendar only.
        $training->participants()->sync([$jan->id]);
        $training->refresh()->touch();
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), '/users/piet%40power.example/events/'));
        $this->assertSame([$jan->id], OutlookEvent::query()->whereMorphedTo('source', $training)->pluck('user_id')->all());
    }
}
