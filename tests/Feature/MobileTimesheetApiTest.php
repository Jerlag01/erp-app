<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\TimesheetDay;
use App\Models\TimesheetWeek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileTimesheetApiTest extends TestCase
{
    use RefreshDatabase;

    protected const MONDAY = '2026-10-12';

    protected User $user;

    protected Team $team;

    /** @var array{Authorization: string} */
    protected array $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->user = User::factory()->withPermissions(['projects' => ['view']])->inTeam($this->team)->create(['email' => 'jan@example.com', 'name' => 'Jan Peeters', 'locale' => 'nl']);
        Employee::factory()->for($this->user)->create();

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'jan@example.com', 'password' => 'password', 'device_id' => 'phone-1'])->assertCreated();
        $this->auth = ['Authorization' => 'Bearer '.$response->json('key').'.'.$response->json('secret')];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rows(?int $projectId = null): array
    {
        return [
            ['kind' => 'start', 'km_departure' => 1000, 'km_arrival' => 1020, 'arrival' => '07:00', 'departure' => '07:30'],
            ['kind' => 'site', 'km_departure' => 1020, 'km_arrival' => 1060, 'arrival' => '08:00', 'departure' => '15:00', 'project_id' => $projectId],
            ['kind' => 'extra', 'km_departure' => 1060, 'km_arrival' => 1070, 'arrival' => '15:10', 'departure' => '15:40', 'description' => 'Extra werf'],
            ['kind' => 'end', 'km_departure' => 1070, 'km_arrival' => 1100, 'arrival' => '16:00', 'departure' => '16:30'],
        ];
    }

    #[Test]
    public function the_employee_sees_the_week_of_their_prestation_sheet(): void
    {
        $this->getJson('/api/v1/timesheet/week?date=2026-10-14', $this->auth)
            ->assertOk()
            ->assertJsonPath('data.monday', self::MONDAY)
            ->assertJsonPath('data.number', 42)
            ->assertJsonPath('data.name', 'Jan Peeters')
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonCount(5, 'data.days')
            ->assertJsonPath('data.days.0.weekday', 'maandag');

        $this->getJson('/api/v1/timesheet/week')->assertUnauthorized();
    }

    #[Test]
    public function a_day_is_filled_in_from_the_app_and_saved_like_on_the_timesheet_page(): void
    {
        $project = Project::factory()->inCategory('600')->create(['number' => 914]);
        PlanningItem::factory()->forProject($project)->create(['user_id' => $this->user->id, 'date' => '2026-10-13']);

        // A new day starts with the planned project on the site row.
        $this->getJson('/api/v1/timesheet/days/2026-10-13', $this->auth)
            ->assertOk()
            ->assertJsonPath('data.saved', false)
            ->assertJsonPath('data.rows.1.project_id', $project->id)
            ->assertJsonPath('data.rows.1.imputation', '600-0914')
            ->assertJsonPath('data.planned_projects.0.imputation', '600-0914');

        $this->putJson('/api/v1/timesheet/days/2026-10-13', ['rows' => $this->rows($project->id), 'lunch_break' => true, 'remarks' => 'Regen'], $this->auth)
            ->assertOk()
            ->assertJsonPath('data.saved', true)
            // Start, site and end count (the extra site not), minus the lunch break: 8 − 0.5 h, 20 + 40 + 30 km.
            ->assertJsonPath('data.worked', 7.5)
            ->assertJsonPath('data.km', 90)
            ->assertJsonPath('data.rows.2.description', 'Extra werf');

        $day = TimesheetDay::query()->sole();
        $this->assertSame(['600-0914', $project->refresh()->cow_code], [$day->rows[1]['imputation'], $day->rows[1]['description']]);
        $this->assertSame(['start', 'site', 'extra', 'end'], array_column($day->rows, 'kind'));
        $this->assertTrue($day->lunch_break);
        $this->assertSame('Regen', $day->remarks);
        $this->assertSame($this->user->id, $day->updated_by);
        $this->assertSame(1, TimesheetWeek::query()->where('user_id', $this->user->id)->count());

        // The week shows the day.
        $this->getJson('/api/v1/timesheet/week?date=2026-10-13', $this->auth)
            ->assertJsonPath('data.days.1.filled', true)
            ->assertJsonPath('data.days.1.total', 7.5)
            ->assertJsonPath('data.hours', 7.5);
    }

    #[Test]
    public function wrong_values_and_other_projects_are_refused(): void
    {
        $rows = $this->rows();
        $rows[0]['km_arrival'] = 900;
        $rows[3]['departure'] = '15:00';

        $this->putJson('/api/v1/timesheet/days/2026-10-13', ['rows' => $rows], $this->auth)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.0.km_arrival', 'rows.3.departure']);

        $foreign = Project::factory()->create();
        $this->putJson('/api/v1/timesheet/days/2026-10-13', ['rows' => $this->rows($foreign->id)], $this->auth)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rows.1.project_id']);

        $this->getJson('/api/v1/timesheet/days/2026-10-17', $this->auth)->assertNotFound();
        $this->assertSame(0, TimesheetDay::query()->count());
    }

    #[Test]
    public function a_sent_week_can_no_longer_be_changed_from_the_app(): void
    {
        TimesheetWeek::query()->create(['user_id' => $this->user->id, 'week_start' => self::MONDAY, 'sent_at' => now()]);

        $this->getJson('/api/v1/timesheet/days/2026-10-13', $this->auth)->assertOk()->assertJsonPath('data.can_edit', false);
        $this->putJson('/api/v1/timesheet/days/2026-10-13', ['rows' => $this->rows()], $this->auth)
            ->assertForbidden()
            ->assertJsonPath('code', 'week_locked');
    }

    #[Test]
    public function projects_are_found_for_the_imputation(): void
    {
        $visible = Project::factory()->inCategory('600')->create(['number' => 915, 'short_description' => 'Laadpaal Gent']);
        $visible->teams()->attach($this->team);
        Project::factory()->inCategory('600')->create(['number' => 916, 'short_description' => 'Laadpaal Brussel']);

        $this->getJson('/api/v1/timesheet/projects?search=Laadpaal', $this->auth)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.imputation', '600-0915');
    }

    #[Test]
    public function the_module_can_be_switched_off(): void
    {
        $this->setModules(['timesheets' => false]);

        $this->getJson('/api/v1/timesheet/week', $this->auth)->assertForbidden()->assertJsonPath('code', 'module_off');
    }
}
