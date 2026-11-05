<?php

namespace Tests\Feature;

use App\Actions\Planning\NotifyPlanningChange;
use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\HeldPlanningNotification;
use App\Models\Holiday;
use App\Models\PlanningItem;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\LeavePushDelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Planning notifications on the phone and by e-mail wait while the employee is on leave (or a public
 * holiday) until the last day of the leave.
 */
class PlanningLeaveDelayTest extends TestCase
{
    use RefreshDatabase;

    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 7 October 2026.
        Carbon::setTestNow('2026-10-07 09:00:00');
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        Mail::fake();
        $this->withoutDefer();

        $this->employee = User::factory()->withPermissions([])->create(['locale' => 'nl', 'email' => 'jan@example.com']);
        $this->mobileAuth($this->employee, 'ExponentPushToken[jan]');
        settings()->save('planning', [...settings('planning'), 'notify_emails' => ['office@example.com']]);
    }

    protected function notify(string $date = '2026-10-12', string $event = NotifyPlanningChange::CREATED): void
    {
        $item = PlanningItem::factory()->forProject()->create(['user_id' => $this->employee->id, 'date' => $date]);
        app(NotifyPlanningChange::class)->handle($this->employee, $event, [$item], User::factory()->admin()->create(), true);
    }

    #[Test]
    public function without_leave_everything_goes_at_once(): void
    {
        $this->notify();

        Http::assertSentCount(1);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('jan@example.com') && $mail->hasCc('office@example.com'));
        $this->assertSame(0, HeldPlanningNotification::query()->count());
    }

    #[Test]
    public function on_leave_the_push_and_mail_wait_until_the_last_day_of_the_leave(): void
    {
        // Leave Monday 5 until Friday 9 October: with the weekend, the last day is Sunday the 11th.
        VacationRequest::factory()->approved()->for($this->employee)->create(['start_date' => '2026-10-05', 'end_date' => '2026-10-09']);

        $this->notify('2026-10-12');
        $this->notify('2026-10-13', NotifyPlanningChange::UPDATED);

        Http::assertNothingSent();
        // The office gets its copy at once; the employee's own e-mail waits.
        Mail::assertSent(SystemMail::class, 2);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('office@example.com') && ! $mail->hasTo('jan@example.com') && ! $mail->hasCc('jan@example.com'));
        $this->assertSame(['2026-10-11 18:00:00'], HeldPlanningNotification::query()->pluck('send_at')->map->toDateTimeString()->unique()->values()->all());
        $this->assertSame(['push', 'mail', 'push', 'mail'], HeldPlanningNotification::query()->orderBy('id')->pluck('channel')->all());

        // Nothing goes before the last day.
        $this->artisan('planning:send-held')->assertSuccessful();
        Http::assertNothingSent();

        // Sunday 18:00: one push for both changes, and both e-mails.
        Carbon::setTestNow('2026-10-11 18:00:00');
        $this->artisan('planning:send-held')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => json_decode($request->body(), true)[0]['title'] === __('erp.planning.notifications.held_title', [], 'nl')
            && json_decode($request->body(), true)[0]['data'] === ['screen' => 'planning', 'date' => '2026-10-12']);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('jan@example.com') && $mail->key === MailTemplateKey::PlanningCreated && str_contains($mail->rendered['html'], '12/10/2026'));
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('jan@example.com') && $mail->key === MailTemplateKey::PlanningUpdated);
        Mail::assertSent(SystemMail::class, 4);
        $this->assertSame(0, HeldPlanningNotification::query()->count());
    }

    #[Test]
    public function a_public_holiday_counts_and_a_longer_leave_moves_the_delivery(): void
    {
        $delay = app(LeavePushDelay::class);

        // A public holiday today (Wednesday): delivered this evening.
        $holiday = Holiday::factory()->create(['date' => '2026-10-07', 'name' => 'Feestdag', 'type' => Holiday::HOLIDAY]);
        $this->assertSame('2026-10-07 18:00:00', $delay->until($this->employee)?->toDateTimeString());
        $this->assertNull($delay->until($this->employee, Carbon::parse('2026-10-07 19:00')), 'After 18:00 on the last day it goes at once.');
        $holiday->delete();

        // A half day of leave or a leave that is not approved yet does not count.
        VacationRequest::factory()->approved()->for($this->employee)->create(['start_date' => '2026-10-07', 'end_date' => '2026-10-07', 'half_day' => true]);
        VacationRequest::factory()->for($this->employee)->create(['start_date' => '2026-10-07', 'end_date' => '2026-10-09']);
        $this->assertNull($delay->until($this->employee));

        // On a weekend joined to a leave: still on leave.
        VacationRequest::factory()->approved()->for($this->employee)->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-14']);
        $this->assertSame('2026-10-14 18:00:00', $delay->until($this->employee, Carbon::parse('2026-10-10 10:00'))?->toDateTimeString());
        $this->assertNull($delay->until($this->employee, Carbon::parse('2026-10-03 10:00')), 'An ordinary weekend is not leave.');

        // Held until Wednesday 14; the leave gets longer meanwhile: the notifications wait for the new end.
        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->notify('2026-10-15');
        VacationRequest::factory()->approved()->for($this->employee)->create(['start_date' => '2026-10-15', 'end_date' => '2026-10-16']);
        Carbon::setTestNow('2026-10-14 18:05:00');
        $this->artisan('planning:send-held')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(['2026-10-18 18:00:00'], HeldPlanningNotification::query()->pluck('send_at')->map->toDateTimeString()->unique()->values()->all());
    }
}
