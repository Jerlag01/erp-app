<?php

namespace Tests\Feature;

use App\Enums\VacationStatus;
use App\Filament\Pages\VacationCalendar as CalendarPage;
use App\Models\Holiday;
use App\Models\Team;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\VacationCalendar;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VacationCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-02 09:00:00');
    }

    /**
     * Names shown on a given day of the November 2026 grid.
     *
     * @return list<string>
     */
    protected function namesOn(string $date, User $viewer, ?int $teamId = null): array
    {
        $calendar = app(VacationCalendar::class)->month(2026, 11, $viewer, $teamId);

        foreach ($calendar['weeks'] as $week) {
            foreach ($week as $day) {
                if ($day['date']->toDateString() === $date) {
                    return array_map(fn (array $entry): string => $entry['request']->user->name, $day['entries']);
                }
            }
        }

        $this->fail("{$date} is not in the grid.");
    }

    #[Test]
    public function the_grid_runs_monday_to_sunday_and_marks_holidays(): void
    {
        Holiday::create(['date' => '2026-11-11', 'name' => 'Wapenstilstand']);

        $calendar = app(VacationCalendar::class)->month(2026, 11, User::factory()->admin()->create());

        $this->assertSame('2026-10-26', $calendar['weeks'][0][0]['date']->toDateString());
        $this->assertSame('2026-12-06', last(last($calendar['weeks']))['date']->toDateString());
        $this->assertCount(6, $calendar['weeks']);
        $this->assertSame('Wapenstilstand', collect($calendar['weeks'])->flatten(1)->firstWhere(fn ($day) => $day['date']->toDateString() === '2026-11-11')['holiday']);
    }

    #[Test]
    public function pending_and_approved_vacation_is_shown_but_rejected_and_withdrawn_is_not(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = VacationRequest::factory()->for(User::factory()->create(['name' => 'Piet Pending']))->create(['start_date' => '2026-11-16', 'end_date' => '2026-11-17']);
        VacationRequest::factory()->approved()->for(User::factory()->create(['name' => 'Anna Approved']))->create(['start_date' => '2026-11-16', 'end_date' => '2026-11-16']);
        VacationRequest::factory()->for(User::factory()->create(['name' => 'Rik Rejected']))->create(['start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'status' => VacationStatus::Rejected]);
        VacationRequest::factory()->for(User::factory()->create(['name' => 'Cas Cancelled']))->create(['start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'status' => VacationStatus::Cancelled]);

        $this->assertEqualsCanonicalizing(['Piet Pending', 'Anna Approved'], $this->namesOn('2026-11-16', $admin));

        // Rejecting a pending request removes it from the calendar.
        $pending->reject($admin, 'Te druk');
        $this->assertSame(['Anna Approved'], $this->namesOn('2026-11-16', $admin));
    }

    #[Test]
    public function vacation_is_not_drawn_on_weekends_or_public_holidays(): void
    {
        Holiday::create(['date' => '2026-11-11', 'name' => 'Wapenstilstand']);
        $admin = User::factory()->admin()->create();
        VacationRequest::factory()->approved()->for(User::factory()->create(['name' => 'Anna']))->create(['start_date' => '2026-11-10', 'end_date' => '2026-11-16']);

        $this->assertSame(['Anna'], $this->namesOn('2026-11-10', $admin));
        $this->assertSame([], $this->namesOn('2026-11-11', $admin));
        $this->assertSame([], $this->namesOn('2026-11-14', $admin));
        $this->assertSame(['Anna'], $this->namesOn('2026-11-16', $admin));
    }

    #[Test]
    public function employees_see_their_own_team_and_admins_can_filter_by_team(): void
    {
        $luik = Team::factory()->create();
        $namen = Team::factory()->create();
        $employee = User::factory()->inTeam($luik)->create(['name' => 'Eva Employee']);
        VacationRequest::factory()->for(User::factory()->inTeam($luik)->create(['name' => 'Lieve Luik']))->create();
        VacationRequest::factory()->for(User::factory()->inTeam($namen)->create(['name' => 'Nico Namen']))->create();
        VacationRequest::factory()->for($employee)->create();

        $this->assertEqualsCanonicalizing(['Eva Employee', 'Lieve Luik'], $this->namesOn('2026-11-18', $employee));

        $admin = User::factory()->admin()->create();
        $this->assertEqualsCanonicalizing(['Eva Employee', 'Lieve Luik', 'Nico Namen'], $this->namesOn('2026-11-18', $admin));
        $this->assertSame(['Nico Namen'], $this->namesOn('2026-11-18', $admin, $namen->id));
    }

    #[Test]
    public function guests_see_only_their_own_vacation_and_the_holidays(): void
    {
        $guest = User::factory()->create(['name' => 'Gina Guest']);
        VacationRequest::factory()->for($guest)->create();
        VacationRequest::factory()->for(User::factory()->inTeam()->create(['name' => 'Other']))->create();

        $this->assertSame(['Gina Guest'], $this->namesOn('2026-11-18', $guest));
    }

    #[Test]
    public function the_page_renders_and_navigates_between_months(): void
    {
        $user = User::factory()->inTeam()->create(['name' => 'Eva Employee']);
        VacationRequest::factory()->for($user)->create();
        Holiday::create(['date' => '2026-12-25', 'name' => 'Kerstmis']);

        $this->actingAs($user)->get('/vacation-calendar')->assertOk()->assertSee('Eva Employee');
        $this->actingAs(User::factory()->admin()->create())->get('/admin/vacation-calendar')->assertOk();

        Filament::setCurrentPanel('app');
        Livewire::actingAs($user)
            ->test(CalendarPage::class)
            ->assertSet('month', '2026-11')
            ->call('nextMonth')
            ->assertSet('month', '2026-12')
            ->assertSee('Kerstmis')
            ->assertDontSee('Eva Employee')
            ->call('thisMonth')
            ->assertSet('month', '2026-11');
    }

    #[Test]
    public function the_calendar_disappears_with_the_vacation_module(): void
    {
        $this->setModules(['vacations' => false]);

        $this->actingAs(User::factory()->admin()->create())->get('/vacation-calendar')->assertForbidden();
    }
}
