<?php

namespace Tests\Feature;

use App\Enums\EmploymentCategory;
use App\Filament\Admin\Resources\Employees\Pages\EditEmployee;
use App\Filament\Admin\Resources\Employees\Pages\ViewEmployee;
use App\Models\Employee;
use App\Models\IncapacityPeriod;
use App\Models\User;
use App\Support\Timesheets;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IncapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');
    }

    /**
     * @return array<string, string>
     */
    protected function statuses(Employee $employee, string $from, string $until): array
    {
        return array_map(fn (array $status): string => $status['type'], app(Timesheets::class)->statuses($employee->user, CarbonImmutable::parse($from), CarbonImmutable::parse($until)));
    }

    #[Test]
    public function arbeidsongeschikt_makes_every_working_day_sickness_and_the_end_is_logged(): void
    {
        $employee = Employee::factory()->create();

        // Set from Monday 9 November on.
        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->fillForm(['employment_category' => EmploymentCategory::Incapacitated->value])
            ->assertFormFieldVisible('incapacity_date')
            ->fillForm(['incapacity_date' => '2026-11-09'])
            ->call('save')
            ->assertHasNoFormErrors();

        $period = IncapacityPeriod::sole();
        $this->assertSame('2026-11-09', $period->start_date->toDateString());
        $this->assertNull($period->end_date);
        $this->assertSame(EmploymentCategory::Worker, $period->previous_category);

        // Working days are Z (medical), weekends stay empty, also in the weeks still to come.
        $week = $this->statuses($employee, '2026-11-09', '2026-11-15');
        $this->assertSame(['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'], array_keys($week));
        $this->assertSame(['medical'], array_values(array_unique($week)));
        $this->assertSame('medical', $this->statuses($employee, '2026-12-01', '2026-12-01')['2026-12-01'] ?? null);
        $this->assertSame([], $this->statuses($employee, '2026-11-06', '2026-11-06'));

        // Back to "arbeider": the last day of sickness is logged.
        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->fillForm(['employment_category' => EmploymentCategory::Worker->value])
            ->assertFormFieldVisible('incapacity_date')
            ->fillForm(['incapacity_date' => '2026-11-18'])
            ->call('save')
            ->assertHasNoFormErrors();

        $period->refresh();
        $this->assertSame('2026-11-18', $period->end_date->toDateString());
        $this->assertSame(EmploymentCategory::Worker, $period->next_category);
        $this->assertSame('medical', $this->statuses($employee, '2026-11-18', '2026-11-18')['2026-11-18'] ?? null);
        $this->assertSame([], $this->statuses($employee, '2026-11-19', '2026-11-20'));

        Livewire::test(ViewEmployee::class, ['record' => $employee->getRouteKey()])->assertSee('09/11/2026 – 18/11/2026');
    }

    #[Test]
    public function without_a_date_it_starts_today_and_ends_yesterday_and_other_changes_need_none(): void
    {
        $employee = Employee::factory()->create();

        $employee->update(['employment_category' => EmploymentCategory::Incapacitated]);
        $this->assertSame('2026-11-20', IncapacityPeriod::sole()->start_date->toDateString());

        // Ending on the day it started: the period keeps at least that day.
        $employee->refresh()->update(['employment_category' => EmploymentCategory::Employee]);
        $this->assertSame('2026-11-20', IncapacityPeriod::sole()->end_date->toDateString());

        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->fillForm(['employment_category' => EmploymentCategory::Worker->value])
            ->assertFormFieldHidden('incapacity_date');
    }
}
