<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Admin\Resources\Employees\RelationManagers\InterimRecuperationDaysRelationManager;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\InterimRecuperationDay;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\OutlookCalendar;
use App\Support\Planning;
use App\Support\Timesheets;
use App\Support\WorkingDays;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InterimRecuperationDayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
        $this->travelTo('2026-10-30 09:00');
    }

    protected function interim(bool $interim = true): User
    {
        $user = User::factory()->inTeam()->create();
        Employee::factory()->for($user)->create(['is_interim' => $interim]);

        return $user;
    }

    #[Test]
    public function an_administrator_takes_company_ir_days_away_from_an_interim_and_assigns_their_own(): void
    {
        Holiday::query()->create(['date' => '2026-11-02', 'name' => 'Inhaalrust', 'type' => Holiday::RECUPERATION]);
        Holiday::query()->create(['date' => '2026-12-28', 'name' => 'Eindejaar', 'type' => Holiday::RECUPERATION]);
        Holiday::query()->create(['date' => '2026-11-11', 'name' => 'Wapenstilstand', 'type' => Holiday::HOLIDAY]);
        $user = $this->interim();
        $component = fn () => Livewire::test(InterimRecuperationDaysRelationManager::class, ['ownerRecord' => $user->employee, 'pageClass' => ViewEmployee::class]);

        $component()
            ->callTableAction('takeAway', data: ['dates' => ['2026-11-02']])
            ->assertHasNoTableActionErrors();
        $component()
            ->callTableAction('assign', data: ['days' => [
                ['date' => '2026-11-05', 'name' => 'Brugdag'],
                // A public holiday, a weekend and a company IR day that still applies: skipped.
                ['date' => '2026-11-11', 'name' => null],
                ['date' => '2026-11-07', 'name' => null],
                ['date' => '2026-12-28', 'name' => null],
            ]])
            ->assertHasNoTableActionErrors()
            ->assertSee('Brugdag');

        $this->assertSame(
            [['2026-11-02', false], ['2026-11-05', true]],
            InterimRecuperationDay::query()->orderBy('date')->get()->map(fn (InterimRecuperationDay $day): array => [$day->date->toDateString(), $day->is_day_off])->all(),
        );

        $monday = CarbonImmutable::parse('2026-11-02');
        $colleague = User::factory()->inTeam()->create();

        // The prestation sheet: the 2nd is a working day for the interim, the 5th an IR day.
        $statuses = app(Timesheets::class)->statuses($user, $monday, $monday->addDays(4));
        $this->assertArrayNotHasKey('2026-11-02', $statuses);
        $this->assertSame('recuperation', $statuses['2026-11-05']['type']);
        $this->assertSame('recuperation', app(Timesheets::class)->statuses($colleague, $monday, $monday->addDays(4))['2026-11-02']['type']);

        // The planning: only their own IR day is blocked; colleagues keep the company IR day.
        $blocked = app(Planning::class)->blockedDays([$user->id, $colleague->id], $monday, $monday->addDays(4), $user);
        $this->assertSame(['2026-11-05' => 'IR · Brugdag'], $blocked[$user->id]);
        $this->assertSame(['2026-11-02' => 'IR · Inhaalrust'], $blocked[$colleague->id]);

        // Days counted for leave or sickness follow their own IR days.
        $this->assertSame(4.0, WorkingDays::count('2026-11-02', '2026-11-06', false, $user->id));
        $this->assertSame(4.0, WorkingDays::count('2026-11-02', '2026-11-06', false, $colleague->id));
        $this->assertSame(2.0, WorkingDays::count('2026-11-02', '2026-11-03', false, $user->id));
        $this->assertSame(1.0, WorkingDays::count('2026-11-02', '2026-11-03', false, $colleague->id));

        // Outlook: the company IR day leaves their calendar, their own day is in it.
        $outlook = app(OutlookCalendar::class);
        $companyDay = Holiday::query()->whereDate('date', '2026-11-02')->sole();
        $this->assertFalse($outlook->recipients($companyDay)->contains('id', $user->id));
        $this->assertTrue($outlook->recipients($companyDay)->contains('id', $colleague->id));
        $own = InterimRecuperationDay::query()->where('is_day_off', true)->sole();
        $this->assertSame([$user->id], $outlook->recipients($own)->pluck('id')->all());
        $this->assertSame('IR · Brugdag', $outlook->entryPayload($own, $user)['subject']);

        // Assigning the taken-away company day again gives it back; undoing the own day removes it.
        $component()->callTableAction('assign', data: ['days' => [['date' => '2026-11-02', 'name' => null]]])->assertHasNoTableActionErrors();
        $component()->callTableAction('delete', $own);
        $this->assertSame(0, InterimRecuperationDay::query()->count());
        $this->assertSame(4.0, WorkingDays::count('2026-11-02', '2026-11-06', false, $user->id));
    }

    #[Test]
    public function the_leave_of_the_interim_is_recounted_when_an_ir_day_changes(): void
    {
        Holiday::query()->create(['date' => '2026-11-02', 'name' => 'Inhaalrust', 'type' => Holiday::RECUPERATION]);
        $user = $this->interim();
        $request = VacationRequest::factory()->for($user)->create(['start_date' => '2026-11-02', 'end_date' => '2026-11-06', 'half_day' => false]);
        $this->assertSame(4.0, (float) $request->fresh()->days);

        $taken = InterimRecuperationDay::query()->create(['user_id' => $user->id, 'date' => '2026-11-02', 'is_day_off' => false]);
        $this->assertSame(5.0, (float) $request->fresh()->days);

        $taken->delete();
        $this->assertSame(4.0, (float) $request->fresh()->days);
    }

    #[Test]
    public function an_interim_can_be_left_without_a_public_holiday_and_get_it_back(): void
    {
        Holiday::query()->create(['date' => '2026-11-11', 'name' => 'Wapenstilstand', 'type' => Holiday::HOLIDAY]);
        $user = $this->interim();
        $colleague = User::factory()->inTeam()->create();
        $component = fn () => Livewire::test(InterimRecuperationDaysRelationManager::class, ['ownerRecord' => $user->employee, 'pageClass' => ViewEmployee::class]);
        $monday = CarbonImmutable::parse('2026-11-09');

        $component()
            ->assertSee('Feestdagen in 2026 (1)')
            ->callTableAction('takeAway', data: ['dates' => ['2026-11-11']])
            ->assertHasNoTableActionErrors()
            ->assertSee('Feestdag weggenomen');

        // A normal working day for the interim: not on the sheet, counted for leave, not in their Outlook.
        $this->assertArrayNotHasKey('2026-11-11', app(Timesheets::class)->statuses($user, $monday, $monday->addDays(4)));
        $this->assertSame('holiday', app(Timesheets::class)->statuses($colleague, $monday, $monday->addDays(4))['2026-11-11']['type']);
        $this->assertSame(5.0, WorkingDays::count('2026-11-09', '2026-11-13', false, $user->id));
        $this->assertSame(4.0, WorkingDays::count('2026-11-09', '2026-11-13', false, $colleague->id));
        $holiday = Holiday::query()->sole();
        $this->assertFalse(app(OutlookCalendar::class)->recipients($holiday)->contains('id', $user->id));
        $this->assertTrue(app(OutlookCalendar::class)->recipients($holiday)->contains('id', $colleague->id));

        // Assigning the date again gives the holiday back.
        $component()->callTableAction('assign', data: ['days' => [['date' => '2026-11-11', 'name' => null]]])->assertHasNoTableActionErrors();
        $this->assertSame(0, InterimRecuperationDay::query()->count());
        $this->assertSame(4.0, WorkingDays::count('2026-11-09', '2026-11-13', false, $user->id));
    }

    #[Test]
    public function only_interims_have_the_ir_days_tab(): void
    {
        $this->assertTrue(InterimRecuperationDaysRelationManager::canViewForRecord($this->interim()->employee, ViewEmployee::class));
        $this->assertFalse(InterimRecuperationDaysRelationManager::canViewForRecord($this->interim(false)->employee, ViewEmployee::class));
    }
}
