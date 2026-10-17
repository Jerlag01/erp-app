<?php

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Enums\VacationStatus;
use App\Enums\VacationType;
use App\Models\Absence;
use App\Models\TimesheetWeek;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\Timesheets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileLeaveApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $admin;

    /** @var array{Authorization: string} */
    protected array $auth;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday; the week starts on Monday 5 October.
        Carbon::setTestNow('2026-10-07 09:00:00');
        Storage::fake('local');
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);
        $this->withoutDefer();

        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->withPermissions([])->create(['vacation_days' => 20, 'locale' => 'nl']);
        $this->auth = $this->mobileAuth($this->user, 'ExponentPushToken[phone-1]');

        // 16 h of overtime carried into this week.
        TimesheetWeek::query()->create(['user_id' => $this->user->id, 'week_start' => '2026-10-05', 'overtime_carried' => 16]);
    }

    #[Test]
    public function the_balances_and_requests_are_shown(): void
    {
        $this->getJson('/api/v1/leave', $this->auth)
            ->assertOk()
            ->assertJsonPath('data.vacation.remaining', 20)
            ->assertJsonPath('data.overtime.balance', 16)
            ->assertJsonPath('data.overtime.available', 16)
            ->assertJsonPath('data.types.1.value', 'overtime');
    }

    #[Test]
    public function overtime_as_paid_leave_is_requested_and_approved_like_vacation(): void
    {
        $this->postJson('/api/v1/leave/requests', ['type' => 'overtime', 'start_date' => '2026-10-19', 'end_date' => '2026-10-19', 'reason' => 'Recup'], $this->auth)
            ->assertCreated()
            ->assertJsonPath('data.type', 'overtime')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.hours', 8);

        $request = VacationRequest::query()->sole();
        // The approvers are told, like for vacation; the vacation allowance is untouched.
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame(20.0, $this->user->vacationBalance(2026)['remaining']);
        $this->assertSame(8.0, app(Timesheets::class)->overtimeBalance($this->user)['available']);

        // More than what is left (8 h) is refused.
        $this->postJson('/api/v1/leave/requests', ['type' => 'overtime', 'start_date' => '2026-10-20', 'end_date' => '2026-10-21'], $this->auth)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');

        // Approved: it becomes an overtime absence ("R" on the prestation sheet); withdrawn: it is gone again.
        $request->approve($this->admin);
        $absence = Absence::query()->sole();
        $this->assertSame(AbsenceType::Overtime, $absence->type);
        $this->assertSame('2026-10-19', $absence->start_date->toDateString());
        $this->assertSame($absence->id, $request->fresh()->absence_id);
        $this->assertSame('overtime', app(Timesheets::class)->days($this->user, Carbon::parse('2026-10-19')->toImmutable(), Carbon::parse('2026-10-19')->toImmutable())['2026-10-19']['status']['type']);

        $this->postJson("/api/v1/leave/requests/{$request->id}/cancel", [], $this->auth)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(0, Absence::query()->count());
        $this->assertSame(16.0, app(Timesheets::class)->overtimeBalance($this->user)['available']);
    }

    #[Test]
    public function a_rejected_overtime_request_leaves_no_absence(): void
    {
        $request = VacationRequest::factory()->for($this->user)->create(['type' => VacationType::Overtime, 'start_date' => '2026-10-19', 'end_date' => '2026-10-19', 'status' => VacationStatus::Approved]);
        $this->assertSame(1, Absence::query()->count());

        $request->reject($this->admin, 'Te druk');
        $this->assertSame(0, Absence::query()->count());
    }

    #[Test]
    public function vacation_is_requested_from_the_app(): void
    {
        $this->postJson('/api/v1/leave/requests', ['type' => 'vacation', 'start_date' => '2026-10-19', 'end_date' => '2026-10-23'], $this->auth)
            ->assertCreated()
            ->assertJsonPath('data.days', 5);

        $this->assertSame(15.0, $this->user->vacationBalance(2026)['remaining']);
    }

    #[Test]
    public function overtime_cannot_be_requested_without_the_prestation_sheets(): void
    {
        $this->setModules(['timesheets' => false]);

        $this->postJson('/api/v1/leave/requests', ['type' => 'overtime', 'start_date' => '2026-10-19', 'end_date' => '2026-10-19'], $this->auth)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    #[Test]
    public function sickness_is_reported_with_a_photo_of_the_certificate(): void
    {
        $this->post('/api/v1/leave/sickness', [
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-09',
            'note' => 'Griep',
            'document' => UploadedFile::fake()->image('attest.jpg'),
        ], [...$this->auth, 'Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'medical')
            ->assertJsonPath('data.has_document', true)
            ->assertJsonPath('data.can_upload', true);

        $absence = Absence::query()->sole();
        $this->assertSame(Absence::SOURCE_EMPLOYEE, $absence->document_source);
        $this->assertSame('attest.jpg', $absence->document_name);
        $this->assertSame(3.0, (float) $absence->days);
        Storage::disk('local')->assertExists($absence->document);
        $this->assertSame(1, $this->admin->notifications()->count());

        // The same days cannot be reported twice.
        $this->postJson('/api/v1/leave/sickness', ['start_date' => '2026-10-08', 'end_date' => '2026-10-08'], $this->auth)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    #[Test]
    public function the_certificate_can_be_added_later_unless_the_administration_did(): void
    {
        $absence = Absence::factory()->for($this->user)->create();

        $this->post("/api/v1/leave/absences/{$absence->id}/document", ['document' => UploadedFile::fake()->create('attest.pdf', 100, 'application/pdf')], [...$this->auth, 'Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.has_document', true);

        $absence->refresh()->update(['document_source' => Absence::SOURCE_ADMIN]);
        $this->post("/api/v1/leave/absences/{$absence->id}/document", ['document' => UploadedFile::fake()->image('new.jpg')], [...$this->auth, 'Accept' => 'application/json'])
            ->assertForbidden();

        $other = Absence::factory()->create();
        $this->post("/api/v1/leave/absences/{$other->id}/document", ['document' => UploadedFile::fake()->image('new.jpg')], [...$this->auth, 'Accept' => 'application/json'])
            ->assertForbidden();
    }

    #[Test]
    public function the_phone_registers_its_push_token(): void
    {
        $this->putJson('/api/v1/push-token', ['token' => 'ExponentPushToken[abc123]'], $this->auth)
            ->assertOk()
            ->assertJsonPath('data.registered', true);
        $this->assertSame('ExponentPushToken[abc123]', $this->user->mobileKeys()->sole()->push_token);

        $this->putJson('/api/v1/push-token', ['token' => 'not-a-token'], $this->auth)->assertUnprocessable();
        $this->putJson('/api/v1/push-token', ['token' => null], $this->auth)->assertOk()->assertJsonPath('data.registered', false);
    }
}
