<?php

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Enums\MailTemplateKey;
use App\Enums\PlanningKind;
use App\Enums\ProjectStatus;
use App\Enums\VacationStatus;
use App\Filament\Pages\Planning;
use App\Mail\SystemMail;
use App\Models\Absence;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\VacationRequest;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        Mail::fake();
    }

    /**
     * @return array<string, mixed>
     */
    protected function projectItem(User $employee, Project $project, array $overrides = []): array
    {
        return [
            'user_ids' => [$employee->id],
            'date' => '2026-10-12',
            'start_time' => '07:30',
            'end_time' => '16:00',
            'kind' => 'project',
            'project_id' => $project->id,
            'notify_mail' => true,
            ...$overrides,
        ];
    }

    #[Test]
    public function a_planner_plans_an_open_project_and_the_employee_is_notified(): void
    {
        settings()->save('planning', [...settings('planning'), 'notify_emails' => ['office@example.com']]);
        $planner = User::factory()->withPermissions(['planning' => ['plan']])->inTeam()->create();
        $employee = User::factory()->inTeam()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        $this->actingAs($planner);

        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project))
            ->assertHasNoActionErrors();

        $item = PlanningItem::sole();
        $this->assertSame($employee->id, $item->user_id);
        $this->assertSame(PlanningKind::Project, $item->kind);
        $this->assertSame('07:30–16:00', $item->timeRange());
        $this->assertSame($planner->id, $item->created_by);

        $this->assertSame(1, $employee->notifications()->count());
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->key === MailTemplateKey::PlanningCreated
            && $mail->hasTo($employee->email)
            && $mail->hasCc('office@example.com')
            && str_contains($mail->rendered['html'], $project->reference));
    }

    #[Test]
    public function only_open_projects_can_be_planned(): void
    {
        $planner = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $offer = Project::factory()->create(['status' => ProjectStatus::Offer]);
        $done = Project::factory()->create(['status' => ProjectStatus::Executed]);
        $this->actingAs($planner);

        foreach ([$offer, $done] as $project) {
            Livewire::test(Planning::class)
                ->callAction('createItem', $this->projectItem($employee, $project))
                ->assertHasActionErrors(['project_id']);
        }

        $this->assertSame(0, PlanningItem::count());
    }

    #[Test]
    public function project_leaders_plan_only_their_own_projects_and_employees_cannot_plan(): void
    {
        $leader = User::factory()->inTeam()->create();
        $employee = User::factory()->inTeam()->create();
        $own = Project::factory()->create(['status' => ProjectStatus::Planned]);
        $own->leaders()->attach($leader);
        $other = Project::factory()->create(['status' => ProjectStatus::Planned]);

        $this->actingAs($leader);
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $other))
            ->assertHasActionErrors(['project_id']);
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $own))
            ->assertHasNoActionErrors();
        // Warehouse jobs and other items need the planning rights.
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $own, ['kind' => 'warehouse', 'description' => 'Sort', 'tasks' => [['text' => 'Sort cables']], 'date' => '2026-10-13']))
            ->assertHasActionErrors(['kind']);

        $this->assertSame(1, PlanningItem::count());

        $this->actingAs($employee);
        Livewire::test(Planning::class)->assertActionHidden('createItem');
    }

    #[Test]
    public function vacation_and_absences_block_the_day(): void
    {
        $planner = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::ToBeExecuted]);
        VacationRequest::factory()->for($employee)->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'status' => VacationStatus::Approved]);
        Absence::factory()->for($employee)->ofType(AbsenceType::Family)->create(['start_date' => '2026-10-14', 'end_date' => '2026-10-14']);
        // A pending request does not block yet.
        VacationRequest::factory()->for($employee)->create(['start_date' => '2026-10-15', 'end_date' => '2026-10-15', 'status' => VacationStatus::Pending]);
        $this->actingAs($planner);

        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project))
            ->assertHasActionErrors(['date']);

        // Over a period the blocked days are skipped: Mon 12 (vacation) and Wed 14 (family leave).
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project, ['until' => '2026-10-16']))
            ->assertHasNoActionErrors();

        $this->assertSame(['2026-10-13', '2026-10-15', '2026-10-16'], PlanningItem::query()->orderBy('date')->get()->map(fn (PlanningItem $item): string => $item->date->toDateString())->all());
    }

    #[Test]
    public function timeslots_of_one_person_cannot_overlap(): void
    {
        $planner = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        PlanningItem::factory()->for($employee)->create(['start_time' => '07:30', 'end_time' => '12:00']);
        $this->actingAs($planner);

        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project, ['start_time' => '11:00', 'end_time' => '15:00']))
            ->assertHasActionErrors(['date']);
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project, ['start_time' => '12:00', 'end_time' => '16:00']))
            ->assertHasNoActionErrors();

        $this->assertSame(2, PlanningItem::count());
    }

    #[Test]
    public function a_warehouse_job_needs_a_description_and_tasks_that_the_employee_ticks_off(): void
    {
        $planner = User::factory()->withPermissions(['planning' => ['plan']])->inTeam()->create();
        $employee = User::factory()->create();
        $this->actingAs($planner);

        Livewire::test(Planning::class)
            ->callAction('createItem', ['user_ids' => [$employee->id], 'date' => '2026-10-12', 'start_time' => '07:30', 'end_time' => '12:00', 'kind' => 'warehouse', 'tasks' => []])
            ->assertHasActionErrors(['description']);

        Livewire::test(Planning::class)
            ->callAction('createItem', ['user_ids' => [$employee->id], 'date' => '2026-10-12', 'start_time' => '07:30', 'end_time' => '12:00', 'kind' => 'warehouse', 'description' => 'Prepare the order for 604-001', 'tasks' => [['text' => 'Pick cables'], ['text' => 'Label boxes']]])
            ->assertHasNoActionErrors();

        $item = PlanningItem::sole();
        $this->assertSame([['text' => 'Pick cables', 'done' => false], ['text' => 'Label boxes', 'done' => false]], $item->taskList());

        $this->actingAs($employee);
        Livewire::test(Planning::class)->call('toggleTask', $item->id, 1);
        $this->assertTrue($item->fresh()->taskList()[1]['done']);

        // Someone else cannot.
        $this->actingAs(User::factory()->create());
        Livewire::test(Planning::class)->call('toggleTask', $item->id, 0)->assertForbidden();
    }

    #[Test]
    public function changes_and_removals_are_notified(): void
    {
        $planner = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        $item = PlanningItem::factory()->for($employee)->forProject($project)->create();
        $this->actingAs($planner);

        Livewire::test(Planning::class)
            ->callAction('editItem', ['start_time' => '08:00', 'notify_mail' => true], ['item' => $item->id])
            ->assertHasNoActionErrors();

        $this->assertSame('08:00', $item->fresh()->startsAt());
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->key === MailTemplateKey::PlanningUpdated && str_contains($mail->rendered['html'], '07:30'));

        Livewire::test(Planning::class)
            ->callAction('editItem', ['notify_mail' => false], ['item' => $item->id, 'delete' => true])
            ->assertHasNoActionErrors();

        $this->assertModelMissing($item);
        Mail::assertSent(SystemMail::class, 1);
        $this->assertSame(2, $employee->notifications()->count());
    }

    #[Test]
    public function team_members_see_each_other_and_shares_add_colleagues(): void
    {
        $team = Team::factory()->create();
        $me = User::factory()->inTeam($team)->create();
        $colleague = User::factory()->inTeam($team)->create();
        $outsider = User::factory()->inTeam()->create();
        $mine = PlanningItem::factory()->for($colleague)->create();
        $theirs = PlanningItem::factory()->for($outsider)->create();
        $this->actingAs($me);

        $this->assertEqualsCanonicalizing([$mine->id], PlanningItem::query()->visibleTo($me)->pluck('id')->all());

        $this->actingAs($outsider);
        Livewire::test(Planning::class)
            ->callAction('share', ['viewers' => [$me->id]])
            ->assertHasNoActionErrors();

        $this->assertEqualsCanonicalizing([$mine->id, $theirs->id], PlanningItem::query()->visibleTo($me)->pluck('id')->all());
        // Sharing is one way: the outsider still doesn't see my team.
        $this->assertEqualsCanonicalizing([$theirs->id], PlanningItem::query()->visibleTo($outsider)->pluck('id')->all());

        $this->assertSame(2, PlanningItem::query()->visibleTo(User::factory()->withPermissions(['planning' => ['view_all']])->inTeam()->create())->count());
    }

    #[Test]
    public function the_planned_employee_can_open_the_project(): void
    {
        $employee = User::factory()->inTeam()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);

        $this->actingAs($employee)->get("/projects/{$project->id}")->assertNotFound();

        PlanningItem::factory()->for($employee)->forProject($project)->create();

        $this->actingAs($employee)->get("/projects/{$project->id}")->assertOk();
        $this->actingAs(User::factory()->inTeam()->create())->get("/projects/{$project->id}")->assertNotFound();
        // Offers and invoices stay hidden.
        $this->assertFalse($employee->can('viewFinance', $project));
    }

    #[Test]
    public function the_calendar_and_list_render(): void
    {
        $team = Team::factory()->create();
        $planner = User::factory()->admin()->create();
        $employee = User::factory()->inTeam($team)->create(['name' => 'Eva Peeters']);
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        PlanningItem::factory()->for($employee)->forProject($project)->create(['date' => '2026-10-12']);
        PlanningItem::factory()->for($employee)->create(['date' => '2026-10-13', 'description' => 'Inventory of the van']);
        Absence::factory()->for($employee)->create(['start_date' => '2026-10-14', 'end_date' => '2026-10-14']);

        foreach (['display=calendar&period=week', 'display=calendar&period=month', 'display=list&period=week'] as $query) {
            $this->actingAs($planner)->get("/planning?date=2026-10-12&{$query}")
                ->assertOk()
                ->assertSee($project->reference)
                ->assertSee('Eva Peeters');
        }

        // A colleague in the team sees "absent", not the medical reason.
        $this->actingAs(User::factory()->inTeam($team)->create())->get('/planning?date=2026-10-12&display=list')
            ->assertOk()
            ->assertSee(__('erp.absences.absent'))
            ->assertDontSee(AbsenceType::Medical->getLabel());

        $this->actingAs($planner)->get('/admin/planning?date=2026-10-12')->assertOk();
    }

    #[Test]
    public function the_module_can_be_switched_off(): void
    {
        $this->setModules(['planning' => false]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/planning')->assertForbidden();
        $this->actingAs($admin)->get('/admin/planning')->assertForbidden();
    }
}
