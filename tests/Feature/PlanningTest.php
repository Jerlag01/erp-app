<?php

namespace Tests\Feature;

use App\Actions\Planning\SavePlanningItems;
use App\Enums\AbsenceType;
use App\Enums\MailTemplateKey;
use App\Enums\PlanningKind;
use App\Enums\ProjectStatus;
use App\Enums\VacationStatus;
use App\Filament\Pages\Planning;
use App\Mail\SystemMail;
use App\Models\Absence;
use App\Models\BatteryDossier;
use App\Models\BatteryModel;
use App\Models\GpwRequest;
use App\Models\Holiday;
use App\Models\InterventionReport;
use App\Models\MoveDossier;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\VacationRequest;
use App\Models\WorkSite;
use App\Support\OutlookCalendar;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
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
        // The tests plan around 12/10/2026: a 604 or intervention planned in the past is executed at once (SyncPlanningStatus).
        $this->travelTo('2026-10-01 08:00');
    }

    /**
     * @return array<string, mixed>
     */
    protected function projectItem(User $employee, Project $project, array $overrides = []): array
    {
        // "No GPW = no planning": these projects have their GPW approved.
        if ($project->needsGpwForPlanning()) {
            GpwRequest::factory()->approved()->for($project)->create();
        }

        return [
            'user_ids' => [$employee->id],
            'date' => '2026-10-12',
            'start_time' => '07:30',
            'end_time' => '16:00',
            'kind' => 'project',
            'project_id' => $project->id,
            'notify_mail' => true,
            // Who may confirm plans directly in the real planning (the others always make a draft).
            'as_draft' => false,
            ...$overrides,
        ];
    }

    #[Test]
    public function a_planner_plans_an_open_project_and_the_employee_is_notified(): void
    {
        settings()->save('planning', [...settings('planning'), 'notify_emails' => ['office@example.com']]);
        $planner = User::factory()->withPermissions(['planning' => ['plan', 'confirm']])->inTeam()->create();
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
    public function a_planner_without_the_confirm_right_makes_a_silent_draft_that_a_confirmer_pushes_to_the_employee(): void
    {
        $planner = User::factory()->withPermissions(['planning' => ['plan']])->inTeam()->create();
        $employee = User::factory()->inTeam()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        $this->actingAs($planner);

        // "Directly" is not offered and ignored: it is a draft.
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project, ['until' => '2026-10-13']))
            ->assertHasNoActionErrors();

        $drafts = PlanningItem::drafts()->get();
        $this->assertCount(2, $drafts);
        $this->assertSame(0, PlanningItem::count());
        $this->assertSame(0, $employee->notifications()->count());
        Mail::assertNothingSent();

        // The planner sees the drafts (dashed); the employee does not, nor in the app.
        Livewire::withQueryParams(['date' => '2026-10-12'])->test(Planning::class)->assertSee(__('erp.planning.draft_badge'))->assertActionHidden('confirmDrafts');
        $this->actingAs($employee);
        Livewire::withQueryParams(['date' => '2026-10-12'])->test(Planning::class)->assertDontSee($project->reference);
        $this->assertFalse($employee->can('view', $drafts->first()));

        // Changing a draft is silent; a confirmed item can't be changed by the planner.
        $this->actingAs($planner);
        app(SavePlanningItems::class)->update($drafts->first(), ['kind' => 'project', 'project_id' => $project->id, 'start_time' => '08:00', 'end_time' => '16:00'], $planner);
        $this->assertSame(0, $employee->notifications()->count());

        // The confirmer confirms the drafts of the week: now the employee gets them.
        $confirmer = User::factory()->withPermissions(['planning' => ['plan', 'confirm']])->inTeam()->create();
        $this->actingAs($confirmer);
        Livewire::withQueryParams(['date' => '2026-10-12'])->test(Planning::class)
            ->assertActionVisible('confirmDrafts')
            ->callAction('confirmDrafts', ['items' => $drafts->pluck('id')->all(), 'notify_mail' => true])
            ->assertHasNoActionErrors();

        $this->assertSame(2, PlanningItem::count());
        $this->assertSame(0, PlanningItem::drafts()->count());
        $this->assertSame($confirmer->id, PlanningItem::query()->first()->confirmed_by);
        $this->assertSame(1, $employee->notifications()->count());
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->key === MailTemplateKey::PlanningCreated && $mail->hasTo($employee->email));

        $this->actingAs($planner);
        $this->assertFalse($planner->can('update', PlanningItem::query()->first()));
        $this->expectException(ValidationException::class);
        app(SavePlanningItems::class)->delete(PlanningItem::query()->first(), $planner, false);
    }

    #[Test]
    public function who_may_confirm_chooses_between_a_draft_and_planning_directly(): void
    {
        $confirmer = User::factory()->withPermissions(['planning' => ['plan', 'confirm']])->inTeam()->create();
        $employee = User::factory()->inTeam()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        $this->actingAs($confirmer);

        Livewire::test(Planning::class)
            ->mountAction('createItem')
            ->assertSchemaStateSet(['as_draft' => true])
            ->assertFormFieldVisible('as_draft');

        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project, ['as_draft' => true]))
            ->assertHasNoActionErrors();
        $draft = PlanningItem::drafts()->sole();

        // A day closed meanwhile (sickness) keeps the draft back.
        Absence::factory()->for($employee)->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-12']);
        $result = app(SavePlanningItems::class)->confirm([$draft], $confirmer);
        $this->assertSame([], $result['confirmed']);
        $this->assertCount(1, $result['skipped']);

        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project, ['date' => '2026-10-13', 'as_draft' => false]))
            ->assertHasNoActionErrors();
        $this->assertSame(1, PlanningItem::query()->whereDate('date', '2026-10-13')->count());
        $this->assertSame(1, $employee->notifications()->count());
    }

    #[Test]
    public function a_battery_move_is_planned_at_its_origin_or_destination_cow_code(): void
    {
        $planner = User::factory()->admin()->create();
        $employee = User::factory()->inTeam()->create();
        $origin = WorkSite::factory()->create(['cow_code' => 'COW-ORIG', 'street' => 'Oude Baan', 'house_number' => '1', 'postal_code' => '9000', 'city' => 'Gent']);
        $destination = WorkSite::factory()->create(['cow_code' => 'COW-DEST', 'street' => 'Nieuwe Baan', 'house_number' => '2', 'postal_code' => '3000', 'city' => 'Leuven']);
        $other = WorkSite::factory()->create(['cow_code' => 'COW-OTHER']);
        $dossier = MoveDossier::factory()->create(['move_from_work_site_id' => $origin->id, 'move_to_work_site_id' => $destination->id]);
        $project = $dossier->project;
        $project->update(['status' => ProjectStatus::InProgress]);
        $this->actingAs($planner);

        // The destination is chosen by default; both COW codes are offered.
        $this->assertSame([$origin->id, $destination->id], array_keys(PlanningItem::moveSiteOptions($project->id)));
        Livewire::test(Planning::class)
            ->mountAction('createItem')
            ->fillForm(['kind' => 'project', 'project_id' => $project->id])
            ->assertSchemaStateSet(['work_site_id' => $destination->id]);

        // Planned at the origin: the route details go there.
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project, ['work_site_id' => $origin->id, 'notify_mail' => false]))
            ->assertHasNoActionErrors();
        $item = PlanningItem::sole();
        $this->assertSame([$origin->id, 'COW-ORIG'], [$item->work_site_id, $item->cowCode()]);
        $this->assertStringContainsString('Oude Baan 1', (string) $item->addressLine());
        $this->assertStringContainsString('Oude Baan 1', app(OutlookCalendar::class)->payload($item)['location']['displayName']);

        $this->getJson('/api/v1/planning?from=2026-10-12&until=2026-10-12&user_id='.$employee->id, $this->mobileAuth($planner))
            ->assertOk()
            ->assertJsonPath('data.days.0.items.0.cow_code', 'COW-ORIG')
            ->assertJsonPath('data.days.0.items.0.address', $origin->addressLine());

        // Another site than the move's is refused; a project without a move has no choice.
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project, ['work_site_id' => $other->id, 'date' => '2026-10-13']))
            ->assertHasActionErrors(['work_site_id']);
        $plain = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        $this->assertSame([], PlanningItem::moveSiteOptions($plain->id));
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $plain, ['date' => '2026-10-14', 'notify_mail' => false]))
            ->assertHasNoActionErrors();
        $this->assertNull(PlanningItem::query()->where('project_id', $plain->id)->value('work_site_id'));
    }

    #[Test]
    public function an_intervention_on_several_cow_codes_is_planned_at_the_cow_code_they_go_to(): void
    {
        $planner = User::factory()->admin()->create();
        $employee = User::factory()->inTeam()->create();
        $main = WorkSite::factory()->create(['cow_code' => '02GAM', 'street' => 'Gamstraat', 'house_number' => '5', 'postal_code' => '1000', 'city' => 'Brussel']);
        $extra = WorkSite::factory()->create(['cow_code' => '02NO1', 'street' => 'Mercuriusstraat', 'house_number' => '27', 'postal_code' => '1930', 'city' => 'Zaventem']);
        $project = Project::factory()->inCategory('600')->for($main)->create(['work_order_number' => '123456', 'status' => ProjectStatus::InProgress]);
        $single = Project::factory()->inCategory('600')->for($extra)->create(['work_order_number' => '654321', 'status' => ProjectStatus::InProgress]);
        $this->actingAs($planner);

        // Only one COW code: nothing to choose.
        $this->assertSame([], PlanningItem::siteOptions($single->id));

        $project->interventionWorkSites()->attach($extra);
        $this->assertSame([$main->id, $extra->id], array_keys(PlanningItem::siteOptions($project->id)));
        Livewire::test(Planning::class)
            ->mountAction('createItem')
            ->fillForm(['kind' => 'project', 'project_id' => $project->id])
            ->assertSchemaStateSet(['work_site_id' => $main->id]);

        // Once the first COW code is closed, the next one is proposed.
        InterventionReport::factory()->for($project)->create(['work_site_id' => $main->id, 'cow_code' => '02GAM']);
        $this->assertSame($extra->id, PlanningItem::defaultSiteId($project->id));

        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project, ['work_site_id' => $extra->id, 'notify_mail' => false]))
            ->assertHasNoActionErrors();
        $item = PlanningItem::sole();
        $this->assertSame([$extra->id, '02NO1'], [$item->work_site_id, $item->cowCode()]);
        $this->assertStringContainsString('Mercuriusstraat 27', (string) $item->addressLine());
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

        // A leader without "planning.confirm" makes a draft.
        $this->assertSame(0, PlanningItem::count());
        $this->assertSame(1, PlanningItem::drafts()->count());

        $this->actingAs($employee);
        Livewire::test(Planning::class)->assertActionHidden('createItem');
    }

    #[Test]
    public function only_sickness_and_family_leave_close_the_day(): void
    {
        $planner = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::ToBeExecuted]);
        // Mon 12 vacation, Tue 13 an IR day, Wed 14 family leave, Thu 15 sick, Fri 16 overtime as leave.
        VacationRequest::factory()->for($employee)->create(['start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'status' => VacationStatus::Approved]);
        Holiday::factory()->create(['date' => '2026-10-13', 'name' => 'Brugdag', 'type' => Holiday::RECUPERATION]);
        Absence::factory()->for($employee)->ofType(AbsenceType::Family)->create(['start_date' => '2026-10-14', 'end_date' => '2026-10-14']);
        Absence::factory()->for($employee)->create(['start_date' => '2026-10-15', 'end_date' => '2026-10-15']);
        Absence::factory()->for($employee)->ofType(AbsenceType::Overtime)->create(['start_date' => '2026-10-16', 'end_date' => '2026-10-16']);
        $this->actingAs($planner);

        // A project on a vacation day can still be planned.
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project))
            ->assertHasNoActionErrors();
        // Not on a day of family leave.
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project, ['date' => '2026-10-14']))
            ->assertHasActionErrors(['date']);

        // Over a period only sickness and family leave are skipped.
        PlanningItem::query()->delete();
        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $project, ['until' => '2026-10-16']))
            ->assertHasNoActionErrors();

        $this->assertSame(['2026-10-12', '2026-10-13', '2026-10-16'], PlanningItem::query()->orderBy('date')->get()->map(fn (PlanningItem $item): string => $item->date->toDateString())->all());

        // The vacation still shows in the planning.
        $this->assertArrayHasKey('2026-10-12', app(\App\Support\Planning::class)->blockedDays([$employee->id], CarbonImmutable::parse('2026-10-12'), CarbonImmutable::parse('2026-10-16'), $planner)[$employee->id]);
    }

    #[Test]
    public function projects_of_one_person_may_overlap(): void
    {
        $planner = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        $other = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        PlanningItem::factory()->forProject($project)->for($employee)->create(['start_time' => '07:30', 'end_time' => '12:00']);
        $this->actingAs($planner);

        Livewire::test(Planning::class)
            ->callAction('createItem', $this->projectItem($employee, $other, ['start_time' => '07:30', 'end_time' => '16:00']))
            ->assertHasNoActionErrors();

        $this->assertSame(2, PlanningItem::count());
    }

    #[Test]
    public function a_warehouse_job_needs_a_description_and_tasks_that_the_employee_ticks_off(): void
    {
        $planner = User::factory()->withPermissions(['planning' => ['plan', 'confirm']])->inTeam()->create();
        $employee = User::factory()->create();
        $this->actingAs($planner);

        Livewire::test(Planning::class)
            ->callAction('createItem', ['user_ids' => [$employee->id], 'date' => '2026-10-12', 'start_time' => '07:30', 'end_time' => '12:00', 'kind' => 'warehouse', 'tasks' => []])
            ->assertHasActionErrors(['description']);

        Livewire::test(Planning::class)
            ->callAction('createItem', ['user_ids' => [$employee->id], 'date' => '2026-10-12', 'start_time' => '07:30', 'end_time' => '12:00', 'kind' => 'warehouse', 'description' => 'Prepare the order for 604-001', 'tasks' => [['text' => 'Pick cables'], ['text' => 'Label boxes']], 'as_draft' => false])
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

        // A colleague in the team sees "absent", not the medical reason (first: within one test the panel's
        // navigation is built once, for the first user).
        $this->actingAs(User::factory()->inTeam($team)->create())->get('/planning?date=2026-10-12&display=list')
            ->assertOk()
            ->assertSee(__('erp.absences.absent'))
            ->assertDontSee(AbsenceType::Medical->getLabel());

        foreach (['display=calendar&period=week', 'display=calendar&period=month', 'display=list&period=week'] as $query) {
            $this->actingAs($planner)->get("/planning?date=2026-10-12&{$query}")
                ->assertOk()
                ->assertSee($project->reference)
                ->assertSee('Eva Peeters');
        }

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

    #[Test]
    public function a_604_project_in_the_planning_shows_the_batteries_that_go_out_and_come_in(): void
    {
        $planner = User::factory()->withPermissions(['planning' => ['plan']])->inTeam()->create();
        $project = Project::factory()->inCategory('604')->create(['status' => ProjectStatus::InProgress]);
        $item = PlanningItem::factory()->forProject($project)->create(['user_id' => $planner->id, 'date' => '2026-10-12']);

        // Without a dossier: only the new batteries of the BAE order.
        $this->assertNull($item->batteries());
        $project->baeOrder->update(['battery_model_id' => BatteryModel::query()->where('name', '7 OPzV 490')->value('id'), 'battery_count' => 2, 'capacity_ah' => 490, 'technology' => 'OPzV']);
        $this->assertSame('-> 2 x 490Ah OPzV', $item->fresh()->batteries());

        BatteryDossier::factory()->create(['project_id' => $project->id, 'old_count' => 3, 'old_capacity_ah' => 500, 'old_technology' => 'OPzS', 'new_count' => 2, 'new_capacity_ah' => 490, 'new_technology' => 'OPzV']);
        $this->assertSame('3 x 500Ah OPzS -> 2 x 490Ah OPzV', $item->fresh()->batteries());

        $this->actingAs($planner);
        Livewire::withQueryParams(['date' => '2026-10-12'])->test(Planning::class)->assertSee('3 x 500Ah OPzS -&gt; 2 x 490Ah OPzV', false);

        // Other projects have no battery line.
        $this->assertNull(PlanningItem::factory()->forProject(Project::factory()->inCategory('600')->create())->create()->batteries());
    }

    #[Test]
    public function each_planning_of_a_project_without_an_approved_gpw_needs_a_reason_and_is_logged(): void
    {
        $planner = User::factory()->withPermissions(['planning' => ['plan', 'confirm']])->inTeam()->create();
        $employee = User::factory()->inTeam()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        GpwRequest::factory()->for($project)->create();
        $data = [...$this->projectItem($employee, Project::factory()->create()), 'project_id' => $project->id];
        $this->actingAs($planner);

        $this->assertTrue($project->needsGpwForPlanning());

        Livewire::test(Planning::class)
            ->callAction('createItem', $data)
            ->assertHasActionErrors(['gpw_bypass_reason' => 'required']);

        Livewire::test(Planning::class)
            ->callAction('createItem', [...$data, 'gpw_bypass_reason' => 'te kort'])
            ->assertHasActionErrors(['gpw_bypass_reason']);

        $this->assertSame(0, PlanningItem::withDrafts()->count());
        $this->assertSame(0, $project->gpwBypasses()->count());

        Livewire::test(Planning::class)
            ->callAction('createItem', [...$data, 'gpw_bypass_reason' => 'Dringende herstelling, klant belde zelf'])
            ->assertHasNoActionErrors();

        $this->assertSame(1, PlanningItem::query()->where('project_id', $project->id)->count());
        $bypass = $project->gpwBypasses()->sole();
        $this->assertSame($planner->id, $bypass->user_id);
        $this->assertSame('Dringende herstelling, klant belde zelf', $bypass->reason);
        $this->assertSame('12/10/2026 · '.$employee->name.' (1)', $bypass->planned);

        // A bypass unlocks nothing: the next planning asks for a reason again and is logged again.
        $this->assertTrue($project->refresh()->needsGpwForPlanning());

        Livewire::test(Planning::class)
            ->callAction('createItem', [...$data, 'date' => '2026-10-13'])
            ->assertHasActionErrors(['gpw_bypass_reason' => 'required']);

        Livewire::test(Planning::class)
            ->callAction('createItem', [...$data, 'date' => '2026-10-13', 'gpw_bypass_reason' => 'Vervolg van de dringende herstelling'])
            ->assertHasNoActionErrors();

        $this->assertSame(2, $project->gpwBypasses()->count());
        $this->assertSame('Vervolg van de dringende herstelling', $project->gpwBypasses()->first()->reason);
    }

    #[Test]
    public function the_bypass_right_plans_without_a_reason_and_interventions_and_approved_gpws_need_none(): void
    {
        $bypasser = User::factory()->withPermissions(['planning' => ['plan', 'confirm', 'bypass_gpw']])->inTeam()->create();
        $planner = User::factory()->withPermissions(['planning' => ['plan', 'confirm']])->inTeam()->create();
        $employee = User::factory()->inTeam()->create();
        $project = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        $intervention = Project::factory()->inCategory('600')->create(['status' => ProjectStatus::InProgress]);
        $approved = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        GpwRequest::factory()->approved()->for($approved)->create();
        $base = $this->projectItem($employee, Project::factory()->create());
        $saver = app(SavePlanningItems::class);

        $saver->create([...$base, 'project_id' => $project->id], $bypasser);
        $this->assertNull($project->gpwBypasses()->sole()->reason);
        $this->assertSame($bypasser->id, $project->gpwBypasses()->sole()->user_id);

        // Without a reason, but still logged every time.
        $saver->create([...$base, 'project_id' => $project->id, 'date' => '2026-10-15'], $bypasser);
        $this->assertSame(2, $project->gpwBypasses()->count());

        $saver->create([...$base, 'project_id' => $intervention->id, 'date' => '2026-10-13'], $planner);
        $saver->create([...$base, 'project_id' => $approved->id, 'date' => '2026-10-14'], $planner);
        $this->assertSame(0, $intervention->gpwBypasses()->count() + $approved->gpwBypasses()->count());
        $this->assertSame(4, PlanningItem::query()->count());
    }

    #[Test]
    public function moving_an_item_to_a_project_without_gpw_needs_a_reason_too(): void
    {
        $planner = User::factory()->withPermissions(['planning' => ['plan', 'confirm']])->inTeam()->create();
        $employee = User::factory()->inTeam()->create();
        $item = PlanningItem::factory()->forProject(Project::factory()->create(['status' => ProjectStatus::InProgress]))->for($employee)->create(['date' => '2026-10-12']);
        $other = Project::factory()->create(['status' => ProjectStatus::InProgress]);
        $saver = app(SavePlanningItems::class);
        $data = ['kind' => 'project', 'project_id' => $other->id, 'user_id' => $employee->id, 'date' => '2026-10-12', 'start_time' => '07:30', 'end_time' => '16:00'];

        try {
            $saver->update($item, $data, $planner);
            $this->fail('A project without GPW was planned.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('gpw_bypass_reason', $exception->errors());
        }

        $saver->update($item->refresh(), [...$data, 'gpw_bypass_reason' => 'Ploeg was al ter plaatse'], $planner);
        $this->assertSame($other->id, $item->refresh()->project_id);
        $this->assertSame('Ploeg was al ter plaatse', $other->gpwBypasses()->sole()->reason);
    }
}
