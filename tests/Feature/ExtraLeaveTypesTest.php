<?php

namespace Tests\Feature;

use App\Actions\SubmitVacationRequest;
use App\Enums\AbsenceType;
use App\Enums\VacationStatus;
use App\Enums\VacationType;
use App\Filament\Admin\Resources\Absences\Pages\ManageAbsences;
use App\Filament\Admin\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Admin\Resources\Employees\RelationManagers\ParentalLeavesRelationManager;
use App\Filament\Admin\Resources\ParentalLeaves\Pages\ManageParentalLeaves;
use App\Filament\Resources\VacationRequests\Pages\ManageVacationRequests;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\ParentalLeave;
use App\Models\Role;
use App\Models\TimesheetDay;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\HrAdvances;
use App\Support\LeaveEntitlements;
use App\Support\Planning;
use App\Support\TimesheetReport;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExtraLeaveTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00'));
        Filament::setCurrentPanel('admin');
    }

    protected function employee(array $attributes = []): User
    {
        $user = User::factory()->inTeam()->create(['vacation_days' => 20]);
        Employee::factory()->for($user)->create($attributes);

        return $user;
    }

    /**
     * A day of prestations with one site row of the given times and kilometres.
     */
    protected function day(User $user, string $date, string $arrival = '07:00', string $departure = '15:30', int $km = 0): TimesheetDay
    {
        $rows = Timesheets::emptyRows();
        $rows[1] = [...$rows[1], 'arrival' => $arrival, 'departure' => $departure, 'km_departure' => 1000, 'km_arrival' => 1000 + $km];

        return TimesheetDay::query()->create(['user_id' => $user->id, 'date' => $date, 'rows' => $rows, 'lunch_break' => true]);
    }

    protected function submit(User $user, VacationType $type, string $from, ?string $until = null, bool $halfDay = false): VacationRequest
    {
        return app(SubmitVacationRequest::class)->handle($user, ['type' => $type->value, 'start_date' => $from, 'end_date' => $until ?? $from, 'half_day' => $halfDay]);
    }

    protected function assertRefused(callable $call, string $field): void
    {
        try {
            $call();
            $this->fail("Expected a validation error on {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    #[Test]
    public function mobility_leave_is_one_day_per_27000_km_of_last_year_and_counts_as_worked(): void
    {
        $user = $this->employee();
        $this->day($user, '2025-03-03', km: 30000);
        $this->day($user, '2025-09-01', km: 25000);

        $mobility = app(LeaveEntitlements::class)->mobility($user, 2026);
        $this->assertSame([55000, 2.0, 2.0, '2026-12-31'], [$mobility['km'], $mobility['earned'], $mobility['remaining'], $mobility['expires']]);
        $this->assertContains(VacationType::Mobility, app(LeaveEntitlements::class)->requestable($user, true));

        $this->assertRefused(fn () => $this->submit($user, VacationType::Mobility, '2026-11-09', '2026-11-11'), 'end_date');
        $request = $this->submit($user, VacationType::Mobility, '2026-11-09', '2026-11-10');
        $request->approve(User::factory()->admin()->create());

        $absence = Absence::query()->where('user_id', $user->id)->sole();
        $this->assertSame(AbsenceType::Mobility, $absence->type);
        $status = app(Timesheets::class)->statuses($user, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-09'))['2026-11-09'];
        $this->assertSame(['mobility', 8.0], [$status['type'], (float) $status['credit']]);
        $this->assertSame('MV', TimesheetReport::CODES['mobility']);

        // Used up; and without kilometres nothing to ask.
        $this->assertSame(0.0, app(LeaveEntitlements::class)->mobility($user, 2026)['remaining']);
        $this->assertNotContains(VacationType::Mobility, app(LeaveEntitlements::class)->requestable($this->employee(), true));
    }

    #[Test]
    public function seniority_leave_is_one_day_a_year_after_25_years_in_service_not_for_interim(): void
    {
        $senior = $this->employee(['employment_date' => '2001-11-01', 'is_interim' => false]);
        $junior = $this->employee(['employment_date' => '2002-01-15']);
        $interim = $this->employee(['employment_date' => '1990-01-01', 'is_interim' => true]);

        $this->assertSame(1.0, app(LeaveEntitlements::class)->seniority($senior, 2026)['earned']);
        $this->assertSame(0.0, app(LeaveEntitlements::class)->seniority($junior, 2026)['earned']);
        $this->assertSame(0.0, app(LeaveEntitlements::class)->seniority($interim, 2026)['earned']);

        $this->submit($senior, VacationType::Seniority, '2026-11-12')->approve(User::factory()->admin()->create());
        $this->assertRefused(fn () => $this->submit($senior, VacationType::Seniority, '2026-11-13'), 'end_date');
        $this->assertRefused(fn () => $this->submit($junior, VacationType::Seniority, '2026-11-13'), 'type');
        $this->assertSame(8.0, (float) app(Timesheets::class)->statuses($senior, CarbonImmutable::parse('2026-11-12'), CarbonImmutable::parse('2026-11-12'))['2026-11-12']['credit']);
    }

    #[Test]
    public function sollicitatieverlof_is_one_full_day_a_week_in_the_notice_period_which_then_ends_the_employment(): void
    {
        $user = $this->employee();
        $employee = $user->employee;
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])
            ->callAction('notice', ['notice_started_on' => '2026-11-02', 'notice_ends_on' => '2026-11-27'])
            ->assertHasNoActionErrors();
        $this->assertTrue($employee->fresh()->isInNotice());
        $this->assertContains(VacationType::JobSearch, app(LeaveEntitlements::class)->requestable($user, true));

        $this->submit($user, VacationType::JobSearch, '2026-11-05');
        $this->assertRefused(fn () => $this->submit($user, VacationType::JobSearch, '2026-11-06'), 'start_date');
        $this->assertRefused(fn () => $this->submit($user, VacationType::JobSearch, '2026-11-09', '2026-11-10'), 'end_date');
        $this->assertRefused(fn () => $this->submit($user, VacationType::JobSearch, '2026-11-12', halfDay: true), 'end_date');
        $this->assertRefused(fn () => $this->submit($user, VacationType::JobSearch, '2026-11-30'), 'start_date');
        $this->submit($user, VacationType::JobSearch, '2026-11-12');
        $this->assertSame(2, VacationRequest::query()->where('type', VacationType::JobSearch)->count());

        // The day after the end date the employee is no longer in service.
        $this->travelTo(CarbonImmutable::parse('2026-11-28 01:00'));
        $this->artisan('employees:end-notice')->assertSuccessful();
        $this->assertSame('2026-11-27', $employee->fresh()->left_on->toDateString());
        $this->assertFalse($user->fresh()->is_active);
    }

    #[Test]
    public function economically_unemployed_and_parental_leave_are_not_worked_but_keep_the_overtime(): void
    {
        $user = $this->employee();
        $timesheets = app(Timesheets::class);
        // Mon–Thu 8 hours worked, Friday economically unemployed: no overtime lost.
        foreach (['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12'] as $date) {
            $this->day($user, $date);
        }
        Absence::factory()->for($user)->ofType(AbsenceType::Economic)->create(['start_date' => '2026-11-13', 'end_date' => '2026-11-13']);

        $week = $timesheets->week($user, '2026-11-09');
        $this->assertSame(32.0, $week['hours']);
        $this->assertSame(32.0, $week['expected']);
        $this->assertSame(0.0, $week['overtime_now']);
        $this->assertSame('EW', TimesheetReport::CODES[$week['days'][4]['status']['type']]);

        // HR advances: not worked (paid by the RVA).
        $result = app(HrAdvances::class)->calculate($user->employee, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-13'), 100);
        $this->assertSame([1.0, 4.0], [$result['rva'], $result['worked']]);
    }

    #[Test]
    public function klein_verlet_needs_a_reason_within_its_days_and_counts_as_worked(): void
    {
        // Real time: uploads in a moved clock count as expired temporary files.
        $this->travelBack();
        $user = $this->employee();
        $this->actingAs(User::factory()->admin()->create());

        // Death of a parent: at most 3 days.
        Livewire::test(ManageAbsences::class)
            ->callAction('create', ['user_id' => $user->id, 'type' => 'short_leave', 'reason' => 'death_parent', 'start_date' => '2026-11-09', 'end_date' => '2026-11-12'])
            ->assertHasActionErrors(['reason']);
        Livewire::test(ManageAbsences::class)
            ->callAction('create', ['user_id' => $user->id, 'type' => 'short_leave', 'reason' => 'death_parent', 'start_date' => '2026-11-09', 'end_date' => '2026-11-11', 'document' => UploadedFile::fake()->create('rouwbrief.pdf', 40, 'application/pdf')])
            ->assertHasNoActionErrors();

        $absence = Absence::query()->sole();
        $this->assertSame([AbsenceType::ShortLeave, 'death_parent'], [$absence->type, $absence->reason]);
        $status = app(Timesheets::class)->statuses($user, CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-10'))['2026-11-10'];
        $this->assertSame(['short_leave', 8.0], [$status['type'], (float) $status['credit']]);
        $this->assertStringContainsString('Overlijden van een (schoon/stief)ouder', $status['note']);
        // Only administration: the employee cannot upload it.
        $this->assertFalse($absence->employeeMayUpload());
    }

    #[Test]
    public function a_work_accident_is_paid_with_its_certificate_within_48_hours_else_unpaid(): void
    {
        $user = $this->employee();
        $incident = Incident::factory()->create();
        $absence = Absence::factory()->for($user)->ofType(AbsenceType::WorkAccident)->create(['start_date' => '2026-11-09', 'end_date' => '2026-11-10', 'incident_id' => $incident->id]);
        $credit = fn (): float => (float) app(Timesheets::class)->statuses($user, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-09'))['2026-11-09']['credit'];

        // Within 48 hours: paid.
        $this->assertTrue($absence->fresh()->isPaidWorkAccident());
        $this->assertSame(8.0, $credit());
        // Nothing can be planned.
        $this->assertNotNull(app(Planning::class)->blockedReason($user, CarbonImmutable::parse('2026-11-09'), $user));

        // After 48 hours without certificate: unpaid.
        $this->travel(49)->hours();
        $this->assertFalse($absence->fresh()->isPaidWorkAccident());
        $this->assertSame(0.0, $credit());
        $result = app(HrAdvances::class)->calculate($user->employee, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-13'), 100);
        $this->assertSame(2.0, $result['unpaid']);

        // With the certificate: paid again.
        $absence->update(['document' => 'absences/x.pdf', 'document_name' => 'attest.pdf']);
        $this->assertSame(8.0, $credit());
    }

    #[Test]
    public function parental_leave_is_enabled_per_employee_and_its_days_fall_within_it(): void
    {
        // Real time: uploads in a moved clock count as expired temporary files.
        $this->travelBack();
        $user = $this->employee();
        $this->actingAs(User::factory()->admin()->create());

        // A child of 12 or older, or longer than the form allows: refused.
        Livewire::test(ParentalLeavesRelationManager::class, ['ownerRecord' => $user->employee, 'pageClass' => ViewEmployee::class])
            ->callTableAction('create', data: ['child_name' => 'Lotte', 'child_birth_date' => '2012-01-01', 'form' => 'full', 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-31', 'document' => UploadedFile::fake()->create('rva.pdf', 40, 'application/pdf')])
            ->assertHasTableActionErrors(['ends_on']);
        Livewire::test(ParentalLeavesRelationManager::class, ['ownerRecord' => $user->employee, 'pageClass' => ViewEmployee::class])
            ->callTableAction('create', data: ['child_name' => 'Lotte', 'child_birth_date' => '2024-05-01', 'form' => 'fifth', 'starts_on' => '2026-11-02', 'ends_on' => '2027-06-30', 'document' => UploadedFile::fake()->create('rva.pdf', 40, 'application/pdf')])
            ->assertHasNoTableActionErrors();
        $leave = ParentalLeave::query()->sole();
        $this->assertSame('fifth', $leave->form);
        $this->assertNotNull(ParentalLeave::problem('full', '2024-05-01', '2026-11-02', '2027-04-01'));

        // OV days only within it.
        Livewire::test(ManageAbsences::class)
            ->callAction('create', ['user_id' => $user->id, 'type' => 'parental', 'parental_leave_id' => $leave->id, 'start_date' => '2026-10-30', 'end_date' => '2026-10-30'])
            ->assertHasActionErrors(['parental_leave_id']);
        Livewire::test(ManageAbsences::class)
            ->callAction('create', ['user_id' => $user->id, 'type' => 'parental', 'parental_leave_id' => $leave->id, 'start_date' => '2026-11-06', 'end_date' => '2026-11-06'])
            ->assertHasNoActionErrors();

        $status = app(Timesheets::class)->statuses($user, CarbonImmutable::parse('2026-11-06'), CarbonImmutable::parse('2026-11-06'))['2026-11-06'];
        $this->assertSame(['parental', 0.0, 8.0], [$status['type'], (float) $status['credit'], (float) $status['expected_off']]);
    }

    #[Test]
    public function night_work_shows_with_an_n_in_the_monthly_prestations(): void
    {
        $user = $this->employee();
        // 22:00 → 06:00 the next morning: 8 night hours (7,5 worked after the lunch break).
        $this->day($user, '2026-11-09', '22:00', '06:00');
        // An early start that goes on into the day is not night work.
        $this->day($user, '2026-11-10', '05:00', '13:30');

        $month = app(TimesheetReport::class)->month($user, CarbonImmutable::parse('2026-11-01'));
        $this->assertSame('7,5N', $month['days'][9]['value']);
        $this->assertSame('8', $month['days'][10]['value']);
        $this->assertSame(8.0, $month['codes']['N']);
    }

    #[Test]
    public function administration_manages_all_leave_without_a_team(): void
    {
        $this->travelBack();
        Filament::setCurrentPanel('app');
        $administration = User::factory()->create(['role_id' => Role::query()->where('name', 'Administratie')->sole()->id]);
        $this->assertSame([], $administration->teamIds());
        $this->assertTrue($administration->managesLeave());
        $employee = $this->employee();
        $this->actingAs($administration);

        // Absences of everyone, on the employee side.
        $this->get('/absences')->assertOk();
        Livewire::test(ManageAbsences::class)
            ->callAction('create', ['user_id' => $employee->id, 'type' => 'economic', 'start_date' => today()->addWeek()->toDateString(), 'end_date' => today()->addWeek()->toDateString()])
            ->assertHasNoActionErrors();
        $this->assertSame(1, Absence::query()->where('user_id', $employee->id)->count());

        // Leave requests of other teams: seen and approved.
        $request = VacationRequest::factory()->for($employee)->create(['status' => VacationStatus::Pending]);
        Livewire::test(ManageVacationRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->callAction(TestAction::make('approve')->table($request));
        $this->assertSame(VacationStatus::Approved, $request->fresh()->status);

        // Parental leave too.
        $this->get('/parental-leaves')->assertOk();
        Livewire::test(ManageParentalLeaves::class)->assertSuccessful();

        // An ordinary employee does not.
        $this->actingAs($this->employee());
        $this->get('/absences')->assertForbidden();
        $this->get('/parental-leaves')->assertForbidden();
    }
}
