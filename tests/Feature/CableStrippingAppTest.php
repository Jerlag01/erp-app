<?php

namespace Tests\Feature;

use App\Enums\PlanningKind;
use App\Models\CableStrippingLine;
use App\Models\PlanningItem;
use App\Models\User;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CableStrippingAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-30 09:00');
    }

    #[Test]
    public function only_employees_planned_in_the_warehouse_today_have_cable_stripping(): void
    {
        $employee = User::factory()->withPermissions([])->inTeam()->create();
        $auth = $this->mobileAuth($employee);

        $this->getJson('/api/v1/me', $auth)->assertJsonPath('data.features.cable_stripping', false);
        $this->getJson('/api/v1/cable-stripping', $auth)->assertForbidden();

        // On a project today: still not.
        PlanningItem::factory()->forProject()->create(['user_id' => $employee->id, 'date' => '2026-10-30']);
        $this->getJson('/api/v1/me', $auth)->assertJsonPath('data.features.cable_stripping', false);

        PlanningItem::factory()->create(['user_id' => $employee->id, 'date' => '2026-10-30', 'kind' => PlanningKind::Warehouse]);
        $this->getJson('/api/v1/me', $auth)->assertJsonPath('data.features.cable_stripping', true);
        $this->getJson('/api/v1/cable-stripping', $auth)->assertOk()->assertJsonPath('data.dates', ['2026-10-30']);
    }

    #[Test]
    public function an_employee_adds_a_line_and_sees_only_their_own_lines(): void
    {
        $employee = User::factory()->withPermissions([])->inTeam()->create();
        $colleague = User::factory()->withPermissions([])->inTeam()->create();
        PlanningItem::factory()->create(['user_id' => $employee->id, 'date' => '2026-10-30']);
        PlanningItem::factory()->create(['user_id' => $employee->id, 'date' => '2026-10-28']);
        WorkSite::factory()->create(['cow_code' => 'ANT123']);
        $auth = $this->mobileAuth($employee);

        $this->getJson('/api/v1/cable-stripping/work-sites?search=ANT', $auth)->assertOk()->assertJsonPath('data.0.cow_code', 'ANT123');

        $this->postJson('/api/v1/cable-stripping', [
            'date' => '2026-10-30',
            'imputation' => 'ANT123',
            'workers' => [$employee->id, $colleague->id],
            'start_time' => '08:00',
            'end_time' => '10:30',
            'weight_before' => 120.5,
            'empty_bin_weight' => 20,
            'weight_after' => 80,
            'bin_number' => 'PI06',
        ], $auth)->assertCreated()
            ->assertJsonPath('data.hours', 2.5)
            ->assertJsonPath('data.cable_before', 100.5)
            ->assertJsonPath('data.stripped', 60)
            ->assertJsonPath('data.waste', 40.5);

        $line = CableStrippingLine::query()->sole();
        $this->assertSame($employee->id, $line->created_by);
        $this->assertEqualsCanonicalizing([$employee->id, $colleague->id], $line->workers()->pluck('users.id')->all());

        // Without workers: the employee themselves. A warehouse day earlier this week is allowed.
        $this->postJson('/api/v1/cable-stripping', ['date' => '2026-10-28'], $auth)->assertCreated()->assertJsonPath('data.workers', $employee->name);

        // Not on a day without warehouse planning, not in the future, end after start.
        $this->postJson('/api/v1/cable-stripping', ['date' => '2026-10-29'], $auth)->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson('/api/v1/cable-stripping', ['date' => '2026-10-31'], $auth)->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson('/api/v1/cable-stripping', ['date' => '2026-10-30', 'start_time' => '10:00', 'end_time' => '09:00'], $auth)->assertJsonValidationErrors('end_time');

        // Someone else's line is not in the list; the colleague's shared line is.
        CableStrippingLine::factory()->create(['date' => '2026-10-30']);
        $this->getJson('/api/v1/cable-stripping', $auth)->assertOk()->assertJsonCount(2, 'data.mine')->assertJsonPath('data.dates', ['2026-10-30', '2026-10-28']);
    }
}
