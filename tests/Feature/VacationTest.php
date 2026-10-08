<?php

namespace Tests\Feature;

use App\Actions\SubmitVacationRequest;
use App\Enums\VacationStatus;
use App\Filament\Admin\Resources\Holidays\Pages\ManageHolidays;
use App\Filament\App\Resources\MyVacations\Pages\ListMyVacations;
use App\Filament\Resources\VacationRequests\Pages\ManageVacationRequests;
use App\Models\Holiday;
use App\Models\Team;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\BelgianHolidays;
use App\Support\WorkingDays;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VacationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 09:00:00');
    }

    #[Test]
    public function weekends_and_public_holidays_are_not_counted(): void
    {
        // Mon 9 Nov – Fri 13 Nov 2026, with Armistice Day on Wednesday 11 Nov.
        Holiday::create(['date' => '2026-11-11', 'name' => 'Wapenstilstand']);

        $this->assertSame(4.0, WorkingDays::count('2026-11-09', '2026-11-13'));
        $this->assertSame(5.0, WorkingDays::count('2026-11-09', '2026-11-16'));
        $this->assertSame(0.0, WorkingDays::count('2026-11-14', '2026-11-15'));
        $this->assertSame(0.5, WorkingDays::count('2026-11-12', '2026-11-12', halfDay: true));
    }

    #[Test]
    public function new_employees_start_with_20_days(): void
    {
        $user = User::factory()->create();

        $this->assertSame(20.0, $user->fresh()->vacationBalance(2026)['remaining']);
    }

    #[Test]
    public function an_employee_requests_vacation_and_an_approver_from_the_team_confirms_it(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->inTeam($team)->create();
        $leader = User::factory()->withPermissions(['vacations' => ['view', 'approve']])->inTeam($team)->create();
        $outsider = User::factory()->withPermissions(['vacations' => ['view', 'approve']])->inTeam()->create();

        $this->actingAs($employee);
        Filament::setCurrentPanel('app');

        Livewire::test(ListMyVacations::class)
            ->callAction('create', ['start_date' => '2026-11-16', 'end_date' => '2026-11-20', 'reason' => 'Herfstreis'])
            ->assertHasNoActionErrors();

        $request = VacationRequest::sole();
        $this->assertSame(VacationStatus::Pending, $request->status);
        $this->assertSame('5.0', $request->days);
        $this->assertSame(['allowance' => 20.0, 'approved' => 0.0, 'pending' => 5.0, 'remaining' => 15.0], $employee->vacationBalance(2026));

        $this->assertCount(1, $leader->notifications);
        $this->assertCount(0, $outsider->notifications);

        // The approver of another team does not even see the request.
        $this->actingAs($outsider);
        Livewire::test(ManageVacationRequests::class)->assertCanNotSeeTableRecords([$request]);

        $this->actingAs($leader);
        Livewire::test(ManageVacationRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->callTableAction('approve', $request, ['review_note' => 'Prima'])
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertSame(VacationStatus::Approved, $request->status);
        $this->assertTrue($request->reviewer->is($leader));
        $this->assertSame(15.0, $employee->vacationBalance(2026)['remaining']);
        $this->assertCount(1, $employee->fresh()->notifications);
    }

    #[Test]
    public function employees_cannot_approve_their_own_request_or_one_without_the_permission(): void
    {
        $team = Team::factory()->create();
        $leader = User::factory()->withPermissions(['vacations' => ['view', 'approve']])->inTeam($team)->create();
        $viewer = User::factory()->withPermissions(['vacations' => ['view']])->inTeam($team)->create();
        $own = VacationRequest::factory()->for($leader)->create();
        $colleagues = VacationRequest::factory()->for(User::factory()->inTeam($team))->create();

        $this->assertFalse($leader->can('approve', $own));
        $this->assertTrue($leader->can('approve', $colleagues));
        $this->assertFalse($viewer->can('approve', $colleagues));
    }

    #[Test]
    public function a_request_cannot_exceed_the_balance_overlap_another_or_span_two_years(): void
    {
        $user = User::factory()->create(['vacation_days' => 3]);
        $submit = fn (string $start, string $end) => app(SubmitVacationRequest::class)->handle($user, ['start_date' => $start, 'end_date' => $end]);

        $this->assertValidationError(fn () => $submit('2026-11-16', '2026-11-20'), 'end_date');
        $submit('2026-11-16', '2026-11-17');
        $this->assertValidationError(fn () => $submit('2026-11-17', '2026-11-17'), 'start_date');
        $this->assertValidationError(fn () => $submit('2026-12-31', '2027-01-04'), 'end_date');
        $this->assertValidationError(fn () => $submit('2026-11-21', '2026-11-22'), 'start_date');
    }

    #[Test]
    public function guests_without_a_team_can_still_request_their_own_vacation(): void
    {
        $guest = User::factory()->create();
        $this->assertTrue($guest->isGuest());

        $this->actingAs($guest)->get('/my-vacation')->assertOk();
        $this->actingAs($guest)->get('/vacation-requests')->assertForbidden();
    }

    #[Test]
    public function only_administrators_change_the_allowance_and_holidays(): void
    {
        $leader = User::factory()->withPermissions(['vacations' => ['view', 'approve']])->inTeam()->create();

        $this->actingAs($leader)->get('/admin/users')->assertForbidden();
        $this->actingAs($leader)->get('/admin/holidays')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/holidays')->assertOk();
    }

    #[Test]
    public function admins_can_add_the_belgian_holidays_and_move_them_freely(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(ManageHolidays::class)
            ->callAction('addBelgianHolidays', ['year' => 2027])
            ->assertHasNoActionErrors();

        $this->assertSame(10, Holiday::whereYear('date', 2027)->count());
        $this->assertTrue(Holiday::whereDate('date', '2027-03-29')->exists(), 'Easter Monday 2027');
        $this->assertTrue(Holiday::whereDate('date', '2027-05-06')->exists(), 'Ascension 2027');
        $this->assertArrayHasKey('2026-04-06', BelgianHolidays::forYear(2026));

        // Running it again adds nothing; a moved holiday stays where the admin put it.
        Holiday::whereDate('date', '2027-07-21')->first()->update(['date' => '2027-07-22']);
        Livewire::test(ManageHolidays::class)->callAction('addBelgianHolidays', ['year' => 2027]);
        $this->assertSame(11, Holiday::whereYear('date', 2027)->count());
    }

    #[Test]
    public function adding_a_holiday_recounts_the_requests_around_it(): void
    {
        $request = VacationRequest::factory()->approved()->create(['start_date' => '2026-11-09', 'end_date' => '2026-11-13']);
        $this->assertSame('5.0', $request->days);

        $holiday = Holiday::create(['date' => '2026-11-11', 'name' => 'Wapenstilstand']);
        $this->assertSame('4.0', $request->fresh()->days);

        $holiday->delete();
        $this->assertSame('5.0', $request->fresh()->days);
    }

    #[Test]
    public function employees_can_withdraw_a_pending_request(): void
    {
        $user = User::factory()->create();
        $request = VacationRequest::factory()->for($user)->create();
        $this->actingAs($user);
        Filament::setCurrentPanel('app');

        Livewire::test(ListMyVacations::class)
            ->callTableAction('cancel', $request)
            ->assertHasNoTableActionErrors();

        $this->assertSame(VacationStatus::Cancelled, $request->fresh()->status);
        $this->assertSame(20.0, $user->vacationBalance(2026)['remaining']);
    }

    protected function assertValidationError(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Expected a validation error on {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
