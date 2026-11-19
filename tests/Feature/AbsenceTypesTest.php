<?php

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Filament\Admin\Resources\Absences\Pages\ManageAbsences;
use App\Filament\Admin\Resources\CustomAbsenceTypes\CustomAbsenceTypeResource;
use App\Filament\Admin\Resources\CustomAbsenceTypes\Pages\ManageCustomAbsenceTypes;
use App\Models\Absence;
use App\Models\CustomAbsenceType;
use App\Models\Employee;
use App\Models\TimesheetDay;
use App\Models\User;
use App\Support\HrAdvances;
use App\Support\Planning;
use App\Support\TimesheetReport;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Weerverlet (WV) and the absence types administration adds themselves, with their own code.
 */
class AbsenceTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    protected function employee(): User
    {
        $user = User::factory()->inTeam()->create(['vacation_days' => 20]);
        Employee::factory()->for($user)->create();

        return $user;
    }

    protected function day(User $user, string $date): TimesheetDay
    {
        $rows = Timesheets::emptyRows();
        $rows[1] = [...$rows[1], 'arrival' => '07:00', 'departure' => '15:30', 'km_departure' => 1000, 'km_arrival' => 1000];

        return TimesheetDay::query()->create(['user_id' => $user->id, 'date' => $date, 'rows' => $rows, 'lunch_break' => true]);
    }

    #[Test]
    public function weerverlet_is_not_worked_but_keeps_the_overtime_and_shows_as_wv(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00'));
        $user = $this->employee();
        foreach (['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12'] as $date) {
            $this->day($user, $date);
        }
        Absence::factory()->for($user)->ofType(AbsenceType::BadWeather)->create(['start_date' => '2026-11-13', 'end_date' => '2026-11-13']);

        $week = app(Timesheets::class)->week($user, '2026-11-09');
        $this->assertSame([32.0, 32.0, 0.0], [$week['hours'], $week['expected'], $week['overtime_now']]);
        $this->assertSame('WV', TimesheetReport::codeOf($week['days'][4]['status']));
        $this->assertSame('WV', app(TimesheetReport::class)->month($user, CarbonImmutable::parse('2026-11-01'))['days'][13]['value']);
        $this->assertSame('Weerverlet', TimesheetReport::legend()['WV']);

        $result = app(HrAdvances::class)->calculate($user->employee, CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-13'), 100);
        $this->assertSame([1.0, 4.0], [$result['rva'], $result['worked']]);
    }

    #[Test]
    public function administration_adds_absence_types_with_their_own_code(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // A code of a fixed type is refused, the code is stored in capitals and only once.
        Livewire::test(ManageCustomAbsenceTypes::class)
            ->callAction('create', ['code' => 'z', 'name' => 'Iets', 'color' => '#0f766e', 'counting' => CustomAbsenceType::UNWORKED])
            ->assertHasActionErrors(['code']);
        Livewire::test(ManageCustomAbsenceTypes::class)
            ->callAction('create', ['code' => 'bv', 'name' => 'Bloedgeven', 'name_fr' => 'Don de sang', 'color' => '#b91c1c', 'counting' => CustomAbsenceType::WORKED, 'blocks_planning' => true, 'is_private' => false])
            ->assertHasNoActionErrors();
        Livewire::test(ManageCustomAbsenceTypes::class)
            ->callAction('create', ['code' => 'BV', 'name' => 'Dubbel', 'color' => '#b91c1c', 'counting' => CustomAbsenceType::UNWORKED])
            ->assertHasActionErrors(['code']);

        $type = CustomAbsenceType::query()->sole();
        $this->assertSame(['BV', 'Don de sang'], [$type->code, $type->label('fr')]);

        // An absence of that type: the type must be chosen.
        $user = $this->employee();
        Livewire::test(ManageAbsences::class)
            ->callAction('create', ['user_id' => $user->id, 'type' => 'custom', 'start_date' => '2026-11-16', 'end_date' => '2026-11-16'])
            ->assertHasActionErrors(['custom_absence_type_id']);
        Livewire::test(ManageAbsences::class)
            ->callAction('create', ['user_id' => $user->id, 'type' => 'custom', 'custom_absence_type_id' => $type->id, 'start_date' => '2026-11-16', 'end_date' => '2026-11-16'])
            ->assertHasNoActionErrors();

        $absence = Absence::query()->sole();
        $this->assertSame(['Bloedgeven', 'BV'], [$absence->typeLabel('nl'), $absence->typeCode()]);
        $this->assertSame('Bloedgeven', $absence->labelFor($this->employee()), 'Not private: colleagues see the reason.');

        // Counted as set up (paid, a day worked), with its own code in the overviews and legend.
        $status = app(Timesheets::class)->statuses($user, CarbonImmutable::parse('2026-11-16'), CarbonImmutable::parse('2026-11-16'))['2026-11-16'];
        $this->assertSame(['custom', 8.0, 'BV'], [$status['type'], (float) $status['credit'], $status['code']]);
        $month = app(TimesheetReport::class)->month($user, CarbonImmutable::parse('2026-11-01'));
        $this->assertSame('BV', $month['days'][16]['value']);
        $this->assertSame(1.0, $month['codes']['BV']);
        $this->assertSame('Bloedgeven', TimesheetReport::legend()['BV']);

        // It blocks the planning, as set up.
        $closed = app(Planning::class)->closedDays([$user->id], CarbonImmutable::parse('2026-11-16'), CarbonImmutable::parse('2026-11-16'), auth()->user());
        $this->assertArrayHasKey('2026-11-16', $closed[$user->id] ?? []);

        // In use: not deleted, only made inactive.
        $this->assertFalse(CustomAbsenceTypeResource::canDelete($type));
    }

    #[Test]
    public function a_type_off_the_expected_hours_keeps_the_overtime(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00'));
        $user = $this->employee();
        foreach (['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12'] as $date) {
            $this->day($user, $date);
        }
        $type = CustomAbsenceType::factory()->create(['code' => 'TW', 'counting' => CustomAbsenceType::KEEPS_OVERTIME]);
        Absence::factory()->for($user)->ofType(AbsenceType::Custom)->create(['custom_absence_type_id' => $type->id, 'start_date' => '2026-11-13', 'end_date' => '2026-11-13']);

        $week = app(Timesheets::class)->week($user, '2026-11-09');
        $this->assertSame([32.0, 32.0, 0.0], [$week['hours'], $week['expected'], $week['overtime_now']]);
        $this->assertSame('TW', TimesheetReport::codeOf($week['days'][4]['status']));
    }
}
