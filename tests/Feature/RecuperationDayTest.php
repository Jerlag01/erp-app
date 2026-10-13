<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Holidays\Pages\ManageHolidays;
use App\Filament\Admin\Resources\RecuperationDays\Pages\ManageRecuperationDays;
use App\Models\Holiday;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\Planning;
use App\Support\TimesheetReport;
use App\Support\Timesheets;
use App\Support\WorkingDays;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecuperationDayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());
    }

    #[Test]
    public function administrators_register_ir_days_separately_from_public_holidays(): void
    {
        Holiday::query()->create(['date' => '2026-11-11', 'name' => 'Wapenstilstand', 'type' => Holiday::HOLIDAY]);

        Livewire::test(ManageRecuperationDays::class)
            ->callAction('addSeveral', ['days' => [
                ['date' => '2026-11-02', 'name' => 'Inhaalrust'],
                ['date' => '2026-12-28', 'name' => 'Inhaalrust eindejaar'],
                ['date' => '2026-11-11', 'name' => 'Inhaalrust'],
            ]])
            ->assertHasNoActionErrors();

        $this->assertSame(['2026-11-02', '2026-12-28'], Holiday::query()->recuperationDays()->orderBy('date')->pluck('date')->map->toDateString()->all());
        $this->assertSame('Wapenstilstand', Holiday::query()->whereDate('date', '2026-11-11')->value('name'), 'A public holiday is not overwritten.');

        Livewire::test(ManageRecuperationDays::class)
            ->filterTable('year', '2026')
            ->assertSee('Inhaalrust eindejaar')
            ->assertDontSee('Wapenstilstand')
            ->callAction('create', ['date' => '2026-11-11', 'name' => 'Inhaalrust'])
            ->assertHasActionErrors(['date' => 'unique']);

        Livewire::test(ManageHolidays::class)
            ->filterTable('year', '2026')
            ->assertSee('Wapenstilstand')
            ->assertDontSee('Inhaalrust eindejaar');

        $this->get('/admin/recuperation-days')->assertOk();
    }

    #[Test]
    public function an_ir_day_is_a_day_off_for_everybody(): void
    {
        Holiday::query()->create(['date' => '2026-11-02', 'name' => 'Inhaalrust', 'type' => Holiday::RECUPERATION]);
        $employee = User::factory()->inTeam()->create();
        $monday = CarbonImmutable::parse('2026-11-02');

        // Not a working day: vacation from Monday to Friday takes 4 days.
        $this->assertSame(4.0, WorkingDays::count('2026-11-02', '2026-11-06'));

        // Nothing can be planned on it.
        $blocked = app(Planning::class)->blockedDays([$employee->id], $monday, $monday->addDays(4), $employee);
        $this->assertSame(['2026-11-02' => 'IR · Inhaalrust'], $blocked[$employee->id]);
        $this->assertArrayHasKey($employee->id, app(Planning::class)->blockedDays(null, $monday, $monday, $employee));

        // A full day IR on the prestation sheet.
        $status = app(Timesheets::class)->statuses($employee, $monday, $monday->addDays(4))['2026-11-02'];
        $this->assertSame(['recuperation', 'Inhaalrust', 8.0, true], [$status['type'], $status['note'], $status['credit'], $status['lunch_locked']]);
        $this->assertSame('IR', TimesheetReport::CODES[$status['type']]);
        $this->assertSame('R', TimesheetReport::CODES['overtime'], 'Overtime taken as time off is R.');
    }

    #[Test]
    public function an_ir_day_overrules_vacation(): void
    {
        $employee = User::factory()->inTeam()->create();
        $request = VacationRequest::factory()->approved()->create(['user_id' => $employee->id, 'start_date' => '2026-11-16', 'end_date' => '2026-11-20', 'days' => 5]);

        // Added after the vacation was approved: that day no longer costs a vacation day.
        Livewire::test(ManageRecuperationDays::class)
            ->callAction('create', ['date' => '2026-11-18', 'name' => 'Inhaalrust'])
            ->assertHasNoActionErrors();

        $this->assertSame(4.0, (float) $request->fresh()->days);

        $monday = CarbonImmutable::parse('2026-11-16');
        $statuses = app(Timesheets::class)->statuses($employee, $monday, $monday->addDays(4));
        ksort($statuses);
        $this->assertSame(['vacation', 'vacation', 'recuperation', 'vacation', 'vacation'], array_column(array_values($statuses), 'type'));
        $this->assertSame('IR · Inhaalrust', app(Planning::class)->blockedDays([$employee->id], $monday, $monday->addDays(4), $employee)[$employee->id]['2026-11-18']);

        // Removing the IR day gives the vacation day back.
        Holiday::query()->recuperationDays()->sole()->delete();
        $this->assertSame(5.0, (float) $request->fresh()->days);
    }
}
