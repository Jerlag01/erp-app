<?php

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Enums\EmploymentCategory;
use App\Enums\VacationStatus;
use App\Enums\VacationType;
use App\Filament\Pages\HrAdvances as HrAdvancesPage;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\HrAdvances;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrAdvancesTest extends TestCase
{
    use RefreshDatabase;

    protected function worker(): Employee
    {
        $user = User::factory()->create();

        return Employee::factory()->create(['user_id' => $user->id, 'employment_category' => EmploymentCategory::Worker]);
    }

    protected function vacation(Employee $employee, string $from, string $until, bool $halfDay = false): void
    {
        VacationRequest::factory()->create(['user_id' => $employee->user_id, 'type' => VacationType::Vacation, 'start_date' => $from, 'end_date' => $until, 'half_day' => $halfDay, 'status' => VacationStatus::Approved]);
    }

    protected function calculate(Employee $employee, float $base = 620): array
    {
        // 1–15 October 2026: 11 working days.
        return app(HrAdvances::class)->calculate($employee, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-15'), $base);
    }

    #[Test]
    public function the_advance_counts_the_days_worked_over_the_working_days(): void
    {
        $employee = $this->worker();
        $this->assertSame(['total' => 11.0, 'worked' => 11.0, 'advance' => 620.0], array_intersect_key($this->calculate($employee), array_flip(['total', 'worked', 'advance'])));

        // Two days of vacation do not count: 620 × 9 / 11.
        $this->vacation($employee, '2026-10-05', '2026-10-06');
        $result = $this->calculate($employee);
        $this->assertSame([2.0, 9.0, 507.27], [$result['vacation'], $result['worked'], $result['advance']]);

        // A public holiday in the vacation counts as worked: one vacation day left, which then counts too.
        Holiday::query()->create(['date' => '2026-10-06', 'type' => 'holiday', 'name' => 'Test']);
        $result = $this->calculate($employee);
        $this->assertTrue($result['vacation_counted']);
        $this->assertSame(620.0, $result['advance']);

        // Family leave and (half a day of) unpaid leave do not count; medical leave does.
        Absence::factory()->create(['user_id' => $employee->user_id, 'type' => AbsenceType::Family, 'start_date' => '2026-10-08', 'end_date' => '2026-10-08', 'half_day' => false, 'days' => 1]);
        Absence::factory()->create(['user_id' => $employee->user_id, 'type' => AbsenceType::Unpaid, 'start_date' => '2026-10-09', 'end_date' => '2026-10-09', 'half_day' => true, 'days' => 0.5]);
        Absence::factory()->create(['user_id' => $employee->user_id, 'type' => AbsenceType::Medical, 'start_date' => '2026-10-12', 'end_date' => '2026-10-14', 'half_day' => false, 'days' => 3]);
        $result = $this->calculate($employee, 1000);
        $this->assertSame([1.0, 0.5, 9.5, 863.64], [$result['family'], $result['unpaid'], $result['worked'], $result['advance']]);
    }

    #[Test]
    public function only_roles_with_the_hr_permission_open_it_and_values_can_be_changed(): void
    {
        $employee = $this->worker();
        Employee::factory()->create(['employment_category' => EmploymentCategory::Employee, 'first_name' => 'Bea', 'last_name' => 'Bediende']);
        Filament::setCurrentPanel('app');

        // Not even an administrator without the permission ticked.
        $this->actingAs(User::factory()->admin()->create())->get(HrAdvancesPage::getUrl())->assertForbidden();

        $this->actingAs(User::factory()->withPermissions(['hr_calculations' => ['view']])->inTeam()->create());
        $this->get(HrAdvancesPage::getUrl())->assertOk()->assertSee($employee->fullName());

        Livewire::test(HrAdvancesPage::class)
            ->set('month', '2026-10')
            ->assertSet('from', '2026-10-01')
            ->assertSet('until', '2026-10-15')
            ->set('bases.workers', 700)
            ->assertSee('€ 700,00')
            ->set("overrides.{$employee->id}", 550)
            ->assertSee('€ 550,00')
            ->call('setGroup', 'servants')
            ->assertSee('Bea Bediende')
            ->assertSee('€ 1.000,00')
            ->assertActionHidden('saveDefaults');

        $this->actingAs(User::factory()->withPermissions(['hr_calculations' => ['view', 'manage']])->inTeam()->create());
        Livewire::test(HrAdvancesPage::class)
            ->set('bases.workers', 650)
            ->set('until', '2026-10-14')
            ->callAction('saveDefaults');
        $this->assertEquals(650, settings()->get('hr.advance_worker'));
        $this->assertSame(14, settings()->get('hr.advance_until_day'));
    }
}
