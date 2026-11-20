<?php

namespace Tests\Feature;

use App\Actions\Gpw\DecideGpw;
use App\Actions\Gpw\PlanGpw;
use App\Actions\Gpw\RequestGpw;
use App\Actions\Planning\SavePlanningItems;
use App\Actions\Projects\CloseProject;
use App\Enums\GpwStatus;
use App\Enums\MailTemplateKey;
use App\Enums\ProjectStatus;
use App\Filament\Client\Resources\GpwRequests\Pages\ListClientGpwRequests;
use App\Filament\Client\Resources\Projects\Pages\ListClientProjects;
use App\Filament\Resources\GpwRequests\GpwRequestResource;
use App\Filament\Resources\GpwRequests\Pages\ListGpwRequests;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Mail\SystemMail;
use App\Models\ClientUser;
use App\Models\GpwRequest;
use App\Models\GpwRequestKey;
use App\Models\MailTemplate;
use App\Models\MoveDossier;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteArea;
use App\Models\WorkSiteContact;
use App\Support\ClientPortal;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GpwRequestTest extends TestCase
{
    use RefreshDatabase;

    protected WorkSiteContact $contact;

    protected function project(ProjectStatus $status = ProjectStatus::ToBeExecuted): Project
    {
        $this->contact ??= WorkSiteContact::factory()->create(['first_name' => 'Sophie', 'last_name' => 'Dubois', 'email' => 'sophie@proximus.example', 'locale' => 'fr']);
        $site = WorkSite::factory()->hasAttached($this->contact, [], 'contacts')->create(['cow_code' => '02GAM']);

        return Project::factory()->inCategory('610')->for($site)->create(['status' => $status]);
    }

    /**
     * An employee with "projects.view" and "projects.gpw", in a team assigned to the project.
     */
    protected function requester(Project $project, string $locale = 'en', ?string $email = null): User
    {
        $team = Team::factory()->create();
        $project->teams()->attach($team);

        return User::factory()->withPermissions(['projects' => ['view', 'gpw']])->inTeam($team)->create(['locale' => $locale, 'email' => $email ?? fake()->unique()->safeEmail()]);
    }

    protected function gpw(Project $project, User $requester, array $data = []): GpwRequest
    {
        Mail::fake();

        return app(RequestGpw::class)->handle($project, $requester, [
            'starts_on' => today()->addWeek()->toDateString(),
            'ends_on' => today()->addWeek()->addDays(2)->toDateString(),
            'remarks' => 'Toegang via achterpoort',
            ...$data,
            'contact_ids' => array_key_exists('contact_ids', $data) ? $data['contact_ids'] : [$this->contact->id],
        ]);
    }

    #[Test]
    public function an_employee_with_the_permission_requests_a_gpw_while_the_project_is_to_be_executed(): void
    {
        Mail::fake();
        $project = $this->project();
        $requester = $this->requester($project, email: 'jan@power.example');
        $this->actingAs($requester);
        Filament::setCurrentPanel('app');

        // The window asks who it goes to: nobody is chosen beforehand.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertActionVisible('requestGpw')
            ->mountAction('requestGpw')
            ->assertSchemaStateSet(['contact_ids' => []]);
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('requestGpw', [
                'starts_on' => today()->addWeek()->toDateString(),
                'ends_on' => today()->addWeek()->addDays(2)->toDateString(),
                'remarks' => 'Toegang via achterpoort',
                'contact_ids' => [$this->contact->id],
            ])
            ->assertHasNoActionErrors();

        $gpw = GpwRequest::query()->sole();
        $this->assertSame(GpwStatus::Pending, $gpw->status);
        $this->assertSame('fr', $gpw->locale);
        $this->assertNotNull($gpw->mailed_at);

        // To the COW site's contact, in their language, with the requester in copy and an approval link.
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('sophie@proximus.example')
            && $mail->hasCc('jan@power.example')
            && $mail->key === MailTemplateKey::GpwRequest
            && str_starts_with($mail->rendered['subject'], 'Demande de GPW')
            && str_contains($mail->rendered['html'], '/gpw/'.$gpw->id.'?expires=')
            && str_contains($mail->rendered['html'], 'Toegang via achterpoort'));

        // At any moment, except for an offer and once the project is being invoiced or closed.
        foreach ([ProjectStatus::WaitingForPo, ProjectStatus::Ordered, ProjectStatus::ToBePlanned, ProjectStatus::Planned, ProjectStatus::InProgress, ProjectStatus::Executed] as $status) {
            Project::query()->whereKey($project->id)->update(['status' => $status->value]);
            Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionVisible('requestGpw')->assertActionVisible('addManualGpw');
        }
        foreach ([ProjectStatus::Offer, ProjectStatus::InvoicingToDo, ProjectStatus::Invoiced] as $status) {
            Project::query()->whereKey($project->id)->update(['status' => $status->value]);
            Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('requestGpw')->assertActionHidden('addManualGpw');
        }
        try {
            GpwRequestResource::addManual(['project_id' => $project->id, 'status' => GpwStatus::Approved->value, 'starts_on' => today()->addWeek()->toDateString(), 'ends_on' => today()->addWeeks(2)->toDateString(), 'contact_name' => 'Sophie']);
            $this->fail('A GPW was added for a project being invoiced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('project_id', $exception->errors());
        }

        // Not without the permission.
        $project->update(['status' => ProjectStatus::Offer]);
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('requestGpw');
        $project->update(['status' => ProjectStatus::ToBeExecuted]);
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view']])->inTeam($project->teams()->first())->create());
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('requestGpw');
    }

    #[Test]
    public function the_edited_template_is_used_and_a_project_without_contact_cannot_be_requested(): void
    {
        $project = $this->project();
        $requester = $this->requester($project);
        MailTemplate::query()->create(['key' => MailTemplateKey::GpwRequest->value, 'locale' => 'fr', 'subject' => 'GPW {cow_code} {period}', 'body' => 'Bonjour {contact_name}', 'is_active' => true]);

        $gpw = $this->gpw($project, $requester);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->rendered['subject'] === 'GPW 02GAM '.$gpw->period());

        // Nobody chosen: nothing goes out automatically.
        $this->expectException(ValidationException::class);
        $this->gpw($project->fresh(), $requester, ['contact_ids' => []]);
    }

    #[Test]
    public function the_contact_approves_through_the_mail_link_and_the_requester_is_told(): void
    {
        $project = $this->project();
        $requester = $this->requester($project, 'nl', 'jan@power.example');
        $gpw = $this->gpw($project, $requester);
        $url = RequestGpw::decisionUrl($gpw);

        // Opening the link changes nothing; the page is in the contact's language.
        $this->get($url)->assertOk()->assertSee($gpw->period())->assertSee('Approuver');
        $this->assertTrue($gpw->fresh()->isPending());
        $this->get(route('gpw.decide', $gpw))->assertForbidden();

        $this->post($url, ['decision' => 'approve'])->assertRedirect();

        $gpw->refresh();
        $this->assertSame(GpwStatus::Approved, $gpw->status);
        $this->assertSame('Sophie Dubois', $gpw->decided_by_name);
        $this->assertSame(ProjectStatus::ToBePlanned, $project->fresh()->status);

        // In the ERP and by e-mail, in the requester's language.
        $this->assertSame(1, $requester->notifications()->count());
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('jan@power.example') && $mail->key === MailTemplateKey::GpwApproved && str_starts_with($mail->rendered['subject'], 'GPW goedgekeurd'));

        // Answered once only.
        $this->post($url, ['decision' => 'reject', 'note' => 'Toch niet'])->assertRedirect();
        $this->assertSame(GpwStatus::Approved, $gpw->fresh()->status);
        $this->get($url)->assertOk()->assertDontSee('name="decision"', false);

        // Refusing needs a reason.
        $project->refresh()->update(['status' => ProjectStatus::ToBeExecuted]);
        $other = $this->gpw($project->fresh(), $requester);
        $this->post(RequestGpw::decisionUrl($other), ['decision' => 'reject'])->assertSessionHasErrors('note');
    }

    #[Test]
    public function a_client_user_approves_the_requests_of_their_own_projects_in_the_portal(): void
    {
        $project = $this->project();
        $requester = $this->requester($project);
        $gpw = $this->gpw($project, $requester);
        $otherGpw = $this->gpw($other = Project::factory()->inCategory('610')->create(['status' => ProjectStatus::ToBeExecuted, 'work_site_id' => WorkSite::factory()->hasAttached(WorkSiteContact::factory(), [], 'contacts')->create()->id]), $this->requester($other));

        $client = ClientUser::factory()->withPermissions([ClientPortal::PROJECTS, ClientPortal::GPW])->create(['work_site_contact_id' => $this->contact->id, 'name' => 'Sophie Dubois']);
        $this->actingAs($client, ClientPortal::GUARD);
        Filament::setCurrentPanel(ClientPortal::PANEL);

        Livewire::test(ListClientGpwRequests::class)
            ->assertCanSeeTableRecords([$gpw])
            ->assertCanNotSeeTableRecords([$otherGpw])
            ->callTableAction('approve', $gpw, ['note' => 'OK, sleutel bij de receptie']);

        $gpw->refresh();
        $this->assertSame(GpwStatus::Approved, $gpw->status);
        $this->assertSame($client->id, $gpw->decided_by_client_user_id);
        $this->assertSame('OK, sleutel bij de receptie', $gpw->decision_note);

        // Without the GPW permission the tab is closed.
        $client->update(['permissions' => [ClientPortal::PROJECTS]]);
        $this->actingAs($client->fresh(), ClientPortal::GUARD);
        $this->get('/client/gpw-requests')->assertForbidden();
    }

    #[Test]
    public function the_requester_plans_a_team_within_the_approved_period_only(): void
    {
        $project = $this->project();
        $requester = $this->requester($project);
        $gpw = $this->gpw($project, $requester, ['starts_on' => today()->next('Monday')->toDateString(), 'ends_on' => today()->next('Monday')->addDays(2)->toDateString()]);
        $gpw->update(['status' => GpwStatus::Approved]);
        $project->update(['status' => ProjectStatus::ToBePlanned]);
        $team = Team::factory()->create(['name' => 'Ploeg uitvoering']);
        $members = User::factory()->count(2)->inTeam($team)->create();

        $data = ['team_id' => $team->id, 'start_time' => '07:30', 'end_time' => '16:00'];

        // Days outside the approved period are refused.
        try {
            app(PlanGpw::class)->handle($gpw, $requester, [...$data, 'date' => $gpw->starts_on->toDateString(), 'until' => $gpw->ends_on->copy()->addDay()->toDateString()]);
            $this->fail('Planned outside the period.');
        } catch (ValidationException) {
        }

        // Someone else without planning rights may not.
        $stranger = User::factory()->withPermissions(['projects' => ['view']])->create();
        $this->assertFalse($gpw->fresh()->canBePlannedBy($stranger));

        $result = app(PlanGpw::class)->handle($gpw->fresh(), $requester, [...$data, 'date' => $gpw->starts_on->toDateString(), 'until' => $gpw->ends_on->toDateString()]);

        $this->assertCount(6, $result['created']);

        // Without "planning.confirm" it is a draft: nothing is planned until someone confirms it.
        $this->assertSame(0, PlanningItem::count());
        $this->assertSame(GpwStatus::Approved, $gpw->fresh()->status);
        $confirmer = User::factory()->withPermissions(['planning' => ['plan', 'confirm']])->inTeam()->create();
        $this->assertCount(6, app(SavePlanningItems::class)->confirm(PlanningItem::drafts()->get(), $confirmer)['confirmed']);

        $this->assertEqualsCanonicalizing($members->pluck('id')->all(), PlanningItem::query()->distinct()->pluck('user_id')->all());
        $this->assertSame(GpwStatus::Planned, $gpw->fresh()->status);
        $this->assertSame(ProjectStatus::Planned, $project->fresh()->status);

        // The client sees the project planned, with the period.
        $client = ClientUser::factory()->withPermissions([ClientPortal::PROJECTS])->create(['work_site_contact_id' => $this->contact->id]);
        $this->actingAs($client, ClientPortal::GUARD);
        Filament::setCurrentPanel(ClientPortal::PANEL);
        Livewire::test(ListClientProjects::class)
            ->assertSee(ProjectStatus::Planned->getLabel())
            ->assertSee(GpwStatus::Planned->getLabel().' · '.$gpw->period());
    }

    #[Test]
    public function a_request_is_planned_once_the_project_is_in_the_planning_within_its_period(): void
    {
        $project = $this->project();
        $requester = $this->requester($project);
        $monday = today()->next('Monday');

        // Already in the planning in the period: approving makes it planned straight away.
        PlanningItem::factory()->forProject($project)->create(['date' => $monday->copy()->addDay()->toDateString(), 'created_by' => $requester->id]);
        $gpw = $this->gpw($project, $requester, ['starts_on' => $monday->toDateString(), 'ends_on' => $monday->copy()->addDays(2)->toDateString()]);
        $this->assertSame(GpwStatus::Pending, $gpw->status, 'Waiting for the client stays waiting.');

        Mail::fake();
        app(DecideGpw::class)->approve($gpw, 'Sophie Dubois');
        $this->assertSame(GpwStatus::Planned, $gpw->fresh()->status);
        $this->assertSame($requester->id, $gpw->fresh()->planned_by);
        // The requester hears it was approved, not refused, even though it is planned already.
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo($requester->email) && $mail->key === MailTemplateKey::GpwApproved);
        Mail::assertNotSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->key === MailTemplateKey::GpwRejected);
        $this->assertSame(__('erp.gpw.notifications.approved_title', ['project' => $project->reference], $requester->preferredLocale() ?? config('app.locale')), $requester->notifications()->latest()->first()->data['title']);
        $this->assertSame(ProjectStatus::Planned, $project->fresh()->status);

        // Approved first, planned later (outside the period does not count).
        $other = Project::factory()->inCategory('610')->for($project->workSite)->create(['status' => ProjectStatus::ToBeExecuted]);
        $second = $this->gpw($other, $this->requester($other), ['starts_on' => $monday->toDateString(), 'ends_on' => $monday->toDateString()]);
        app(DecideGpw::class)->approve($second, 'Sophie Dubois');
        $this->assertSame(GpwStatus::Approved, $second->fresh()->status);

        $item = PlanningItem::factory()->forProject($other)->create(['date' => $monday->copy()->addWeek()->toDateString()]);
        $this->assertSame(GpwStatus::Approved, $second->fresh()->status);

        $item->update(['date' => $monday->toDateString()]);
        $this->assertSame(GpwStatus::Planned, $second->fresh()->status);
        $this->assertSame(ProjectStatus::Planned, $other->fresh()->status);
    }

    #[Test]
    public function a_request_is_in_progress_once_a_planned_day_has_come_and_executed_once_the_project_is_archived(): void
    {
        $project = $this->project();
        $requester = $this->requester($project);
        $monday = today()->next('Monday');
        $gpw = $this->gpw($project, $requester, ['starts_on' => $monday->toDateString(), 'ends_on' => $monday->copy()->addDays(2)->toDateString()]);
        app(DecideGpw::class)->approve($gpw, 'Sophie Dubois');
        PlanningItem::factory()->forProject($project)->create(['date' => $monday->copy()->addDay()->toDateString()]);
        $this->assertSame(GpwStatus::Planned, $gpw->fresh()->status);

        // The first day of the period: nothing planned yet that day, still planned.
        $this->travelTo($monday);
        $this->artisan('gpw:sync-statuses')->assertSuccessful();
        $this->assertSame(GpwStatus::Planned, $gpw->fresh()->status);

        // The planned day: in progress, the project too; it stays so after the period.
        $this->travelTo($monday->copy()->addDay());
        $this->artisan('gpw:sync-statuses')->assertSuccessful();
        $this->assertSame(GpwStatus::InProgress, $gpw->fresh()->status);
        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
        $this->travelTo($monday->copy()->addWeeks(2));
        $this->artisan('gpw:sync-statuses')->assertSuccessful();
        $this->assertSame(GpwStatus::InProgress, $gpw->fresh()->status);

        // Archived: executed; reopened: back in progress.
        $project->fresh()->update(['status' => ProjectStatus::Closed]);
        $this->assertSame(GpwStatus::Executed, $gpw->fresh()->status);
        app(CloseProject::class)->reopen($project->fresh(), ProjectStatus::InProgress);
        $this->assertSame(GpwStatus::InProgress, $gpw->fresh()->status);

        // The planning removed: back to approved.
        PlanningItem::query()->where('project_id', $project->id)->get()->each->delete();
        $this->assertSame(GpwStatus::Approved, $gpw->fresh()->status);
    }

    #[Test]
    public function an_administrator_sets_the_status_by_hand_and_it_is_no_longer_changed(): void
    {
        $project = $this->project();
        $requester = $this->requester($project);
        $monday = today()->next('Monday');
        $gpw = $this->gpw($project, $requester, ['starts_on' => $monday->toDateString(), 'ends_on' => $monday->toDateString()]);
        app(DecideGpw::class)->approve($gpw, 'Sophie Dubois');

        // Only administrators.
        $this->actingAs($requester);
        Livewire::test(ListGpwRequests::class)->assertTableActionHidden('setStatus', $gpw);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Livewire::test(ListGpwRequests::class)
            ->callTableAction('setStatus', $gpw, ['status' => GpwStatus::Executed->value, 'locked' => true])
            ->assertHasNoTableActionErrors();
        $this->assertSame(GpwStatus::Executed, $gpw->fresh()->status);
        $this->assertSame($admin->id, $gpw->fresh()->status_locked_by);

        // Planned and the days going by change nothing.
        PlanningItem::factory()->forProject($project)->create(['date' => $monday->toDateString()]);
        $this->travelTo($monday);
        $this->artisan('gpw:sync-statuses')->assertSuccessful();
        $this->assertSame(GpwStatus::Executed, $gpw->fresh()->status);
        $this->assertSame(ProjectStatus::ToBePlanned, $project->fresh()->status);

        // Handed back: the automatic check takes over at once.
        Livewire::test(ListGpwRequests::class)
            ->callTableAction('setStatus', $gpw, ['status' => GpwStatus::Approved->value, 'locked' => false])
            ->assertHasNoTableActionErrors();
        $this->assertFalse($gpw->fresh()->isLocked());
        $this->assertSame(GpwStatus::InProgress, $gpw->fresh()->status);
        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
    }

    #[Test]
    public function an_unsigned_or_expired_link_does_not_open(): void
    {
        $project = $this->project();
        $gpw = $this->gpw($project, $this->requester($project));

        $this->get(URL::temporarySignedRoute('gpw.decide', now()->subMinute(), ['gpwRequest' => $gpw->id]))->assertForbidden();
        $this->post(route('gpw.decide', $gpw), ['decision' => 'approve'])->assertForbidden();
        $this->assertTrue($gpw->fresh()->isPending());
    }

    #[Test]
    public function every_contact_of_the_area_gets_the_request_in_their_own_language(): void
    {
        Mail::fake();
        $dutch = WorkSiteContact::factory()->create(['first_name' => 'Marc', 'last_name' => 'Peeters', 'email' => 'marc@proximus.example', 'locale' => 'nl']);
        $french = WorkSiteContact::factory()->create(['first_name' => 'Sophie', 'last_name' => 'Dubois', 'email' => 'sophie@proximus.example', 'locale' => 'fr']);
        $noMail = WorkSiteContact::factory()->create(['email' => null]);
        $area = WorkSiteArea::factory()->create();
        $area->contacts()->attach([$dutch->id, $french->id, $noMail->id]);
        $site = WorkSite::factory()->create(['work_site_area_id' => $area->id]);
        $project = Project::factory()->inCategory('610')->for($site)->create(['status' => ProjectStatus::ToBeExecuted]);
        $requester = $this->requester($project, email: 'jan@power.example');

        $gpw = $this->gpw($project, $requester, ['contact_ids' => [$dutch->id, $french->id, $noMail->id]]);

        $this->assertSame('marc@proximus.example, sophie@proximus.example', $gpw->contact_email);
        Mail::assertSent(SystemMail::class, 2);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('marc@proximus.example') && $mail->hasCc('jan@power.example') && str_starts_with($mail->rendered['subject'], 'GPW-aanvraag'));
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('sophie@proximus.example') && ! $mail->hasCc('jan@power.example') && str_starts_with($mail->rendered['subject'], 'Demande de GPW'));

        // The French contact's link opens the page in French.
        $this->get(RequestGpw::decisionUrl($gpw, 'fr'))->assertOk()->assertSee('Approuver');
        $this->get(RequestGpw::decisionUrl($gpw, 'nl'))->assertOk()->assertSee('Goedkeuren');

        // A client user linked to either contact sees the project.
        $client = ClientUser::factory()->withPermissions([ClientPortal::PROJECTS, ClientPortal::GPW])->create(['work_site_contact_id' => $french->id]);
        $this->actingAs($client, ClientPortal::GUARD);
        Filament::setCurrentPanel(ClientPortal::PANEL);
        Livewire::test(ListClientGpwRequests::class)->assertCanSeeTableRecords([$gpw]);
    }

    #[Test]
    public function the_requester_chooses_among_the_sites_own_contacts_and_those_of_the_area(): void
    {
        Mail::fake();
        $area = WorkSiteArea::factory()->create(['name' => '1-VLA']);
        $areaContact = WorkSiteContact::factory()->create(['first_name' => 'Ann', 'last_name' => 'Area', 'email' => 'ann@proximus.example', 'locale' => 'nl']);
        $area->contacts()->attach($areaContact);
        $own = WorkSiteContact::factory()->create(['first_name' => 'Bert', 'last_name' => 'Site', 'email' => 'bert@proximus.example', 'locale' => 'nl']);
        $second = WorkSiteContact::factory()->create(['first_name' => 'Cleo', 'last_name' => 'Site', 'email' => 'cleo@proximus.example', 'locale' => 'en']);
        $withoutMail = WorkSiteContact::factory()->create(['email' => null]);
        $site = WorkSite::factory()->for($area, 'area')->create(['cow_code' => '05ABC']);
        $site->contacts()->attach([$own->id, $second->id, $withoutMail->id]);
        $project = Project::factory()->inCategory('610')->for($site)->create(['status' => ProjectStatus::ToBeExecuted]);
        $stranger = WorkSiteContact::factory()->create(['email' => 'other@example.com']);
        $requester = $this->requester($project);
        $this->actingAs($requester);
        Filament::setCurrentPanel('app');

        // The site's and the area's contacts are suggested (people without e-mail are not), none chosen beforehand.
        $this->assertEqualsCanonicalizing([$own->id, $second->id, $areaContact->id], array_keys(RequestGpw::candidateOptions($project)));
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->mountAction('requestGpw')
            ->assertSchemaStateSet(['contact_ids' => []]);

        $dates = ['starts_on' => today()->addWeek()->toDateString(), 'ends_on' => today()->addWeek()->addDay()->toDateString()];
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('requestGpw', [...$dates, 'contact_ids' => []])
            ->assertHasActionErrors(['contact_ids']);
        // Anyone with an e-mail address can be chosen, also a contact of another site, or a new person
        // added in the window (who has no client portal login).
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('requestGpw', [...$dates, 'contact_ids' => [$second->id, $stranger->id]])
            ->assertHasNoActionErrors();
        $this->assertEqualsCanonicalizing([$second->id, $stranger->id], GpwRequest::query()->sole()->contact_ids);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('other@example.com'));

        // Someone without e-mail address is no recipient.
        try {
            app(RequestGpw::class)->handle($project, $requester, [...$dates, 'contact_ids' => [$withoutMail->id]]);
            $this->fail('A request without any e-mail address was made.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contact_ids', $exception->errors());
        }

        // A new person, added from the request window.
        GpwRequest::query()->delete();
        Mail::fake();
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->mountAction('requestGpw')
            ->assertSchemaComponentExists('contact_ids');
        $newPerson = WorkSiteContact::query()->create(['first_name' => 'Nina', 'last_name' => 'Nieuw', 'email' => 'nina@extern.example', 'locale' => 'en']);
        $this->assertArrayHasKey($newPerson->id, RequestGpw::searchRecipients('nina'));
        $this->assertArrayNotHasKey($withoutMail->id, RequestGpw::searchRecipients((string) $withoutMail->last_name));
        GpwRequest::query()->delete();
        Mail::fake();

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('requestGpw', [...$dates, 'contact_ids' => [$second->id, $areaContact->id]])
            ->assertHasNoActionErrors();

        // Only the chosen people of this site and its area get it (each in their own language).
        $gpw = GpwRequest::query()->sole();
        $this->assertEqualsCanonicalizing([$second->id, $areaContact->id], $gpw->contact_ids);
        Mail::assertSent(SystemMail::class, 2);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('cleo@proximus.example'));
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('ann@proximus.example'));
        Mail::assertNotSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('bert@proximus.example') || $mail->hasTo('other@example.com'));

        // Sending it again goes to the same people, also when the site's contacts changed meanwhile.
        $site->contacts()->detach($second);
        Mail::fake();
        $this->assertTrue(app(RequestGpw::class)->mail($gpw->fresh()));
        Mail::assertSent(SystemMail::class, 2);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('cleo@proximus.example'));
    }

    /**
     * The "Approve" and "Refuse" links per recipient of the mails sent so far.
     *
     * @return array<string, array{approve: string, reject: string}>
     */
    protected function directLinks(): array
    {
        $links = [];
        Mail::assertSent(SystemMail::class, function (SystemMail $mail) use (&$links): bool {
            preg_match('#href="([^"]+/gpw/key/[A-Za-z0-9]{48}/approve)"#', $mail->rendered['html'], $approve);
            preg_match('#href="([^"]+/gpw/key/[A-Za-z0-9]{48}/reject)"#', $mail->rendered['html'], $reject);
            $links[$mail->to[0]['address']] = ['approve' => html_entity_decode($approve[1] ?? ''), 'reject' => html_entity_decode($reject[1] ?? '')];

            return true;
        });

        return $links;
    }

    #[Test]
    public function each_recipient_approves_or_refuses_straight_from_the_mail_with_a_personal_key(): void
    {
        $project = $this->project();
        $other = WorkSiteContact::factory()->create(['first_name' => 'Marc', 'last_name' => 'Peeters', 'email' => 'marc@proximus.example', 'locale' => 'nl']);
        $project->workSite->contacts()->attach($other);
        $gpw = $this->gpw($project, $this->requester($project), ['contact_ids' => [$this->contact->id, $other->id]]);
        $links = $this->directLinks();

        // Each recipient has their own links; the keys themselves are not stored.
        $this->assertNotSame($links['sophie@proximus.example']['approve'], $links['marc@proximus.example']['approve']);
        $this->assertSame(2, GpwRequestKey::query()->where('gpw_request_id', $gpw->id)->count());
        $this->assertStringNotContainsString(GpwRequestKey::query()->value('token_hash'), $links['sophie@proximus.example']['approve']);

        // Without logging in, in the recipient's language. Opening either link decides nothing: the page has both buttons.
        $page = $this->get($links['marc@proximus.example']['approve'])->assertOk()
            ->assertSee('name="decision" value="approve"', escape: false)->assertSee('name="decision" value="reject"', escape: false)
            ->assertDontSee('.submit()', escape: false)->assertSee('Marc Peeters')
            ->assertSee('<strong>'.e(__('erp.gpw.page.internal_only', [], 'nl')).'</strong>', escape: false)
            ->assertSee('<p class="warning" role="alert">Zo lang dat u deze niet goedkeurt, kan het project bij ons niet in uitvoering gaan.</p>', escape: false);
        $this->get($links['marc@proximus.example']['reject'])->assertOk()->assertSee('name="decision" value="approve"', escape: false);
        $token = substr(parse_url($links['marc@proximus.example']['approve'], PHP_URL_PATH), strlen('/gpw/key/'), 48);
        $this->assertSame(GpwStatus::Pending, $gpw->fresh()->status, 'Opening the links alone decides nothing.');
        $this->post(route('gpw.key.decide', $token), ['decision' => 'approve'])->assertOk()->assertSee(GpwStatus::Approved->getLabel())->assertDontSee(__('erp.gpw.page.blocks_execution', [], 'nl'));

        $gpw->refresh();
        $this->assertSame([GpwStatus::Approved, 'Marc Peeters'], [$gpw->status, $gpw->decided_by_name]);

        // Every key of the request is used now: the links send the client to the portal login.
        $this->get($links['marc@proximus.example']['approve'])->assertRedirect(ClientPortal::loginUrl());
        $this->get($links['sophie@proximus.example']['reject'])->assertRedirect(ClientPortal::loginUrl());
        $this->post(route('gpw.key.decide', $token), ['decision' => 'reject', 'note' => 'Toch niet'])->assertRedirect(ClientPortal::loginUrl());
        $this->assertSame(GpwStatus::Approved, $gpw->fresh()->status);
        $this->get('/gpw/key/'.str_repeat('a', 48).'/approve')->assertRedirect(ClientPortal::loginUrl());
    }

    #[Test]
    public function refusing_asks_the_reason_and_a_new_mailing_replaces_the_old_keys(): void
    {
        $project = $this->project();
        $gpw = $this->gpw($project, $this->requester($project));
        $old = $this->directLinks()['sophie@proximus.example'];

        // Sent again: the earlier links stop working.
        Mail::fake();
        app(RequestGpw::class)->mail($gpw->fresh());
        $links = $this->directLinks()['sophie@proximus.example'];
        $this->get($old['reject'])->assertRedirect(ClientPortal::loginUrl());

        $token = substr(parse_url($links['reject'], PHP_URL_PATH), strlen('/gpw/key/'), 48);
        $this->get($links['reject'])->assertOk()->assertSee(__('erp.gpw.page.reject_reason', [], 'fr'));
        $this->from($links['reject'])->post(route('gpw.key.decide', $token), ['decision' => 'reject'])->assertRedirect($links['reject'])->assertSessionHasErrors('note');
        $this->post(route('gpw.key.decide', $token), ['decision' => 'reject', 'note' => 'Pas possible cette semaine'])->assertOk();

        $gpw->refresh();
        $this->assertSame([GpwStatus::Rejected, 'Sophie Dubois', 'Pas possible cette semaine'], [$gpw->status, $gpw->decided_by_name, $gpw->decision_note]);

        // An expired key does not work either.
        $expired = $this->gpw($project, $this->requester($project));
        $link = $this->directLinks()['sophie@proximus.example']['approve'];
        GpwRequestKey::query()->where('gpw_request_id', $expired->id)->update(['expires_at' => now()->subMinute()]);
        $this->get($link)->assertRedirect(ClientPortal::loginUrl());
    }

    #[Test]
    public function several_recipients_get_one_mail_together_and_see_who_it_went_to(): void
    {
        $project = $this->project();
        $marc = WorkSiteContact::factory()->create(['first_name' => 'Marc', 'last_name' => 'Peeters', 'email' => 'marc@proximus.example', 'locale' => 'fr']);
        $nl = WorkSiteContact::factory()->create(['first_name' => 'Ann', 'last_name' => 'Claes', 'email' => 'ann@proximus.example', 'locale' => 'nl']);
        $requester = $this->requester($project, email: 'jan@power.example');
        $gpw = $this->gpw($project, $requester, ['contact_ids' => [$this->contact->id, $marc->id, $nl->id]]);

        // Sophie and Marc (both French) in one mail, Ann (Dutch) in her own; everyone sees all three.
        Mail::assertSent(SystemMail::class, 2);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('sophie@proximus.example') && $mail->hasTo('marc@proximus.example')
            && $mail->hasCc('jan@power.example')
            && str_contains(strip_tags($mail->rendered['html']), 'Sophie Dubois (sophie@proximus.example), Marc Peeters (marc@proximus.example), Ann Claes (ann@proximus.example)'));
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('ann@proximus.example') && ! $mail->hasTo('sophie@proximus.example')
            && str_contains(strip_tags($mail->rendered['html']), 'Marc Peeters (marc@proximus.example)'));
        $this->assertSame(2, GpwRequestKey::query()->where('gpw_request_id', $gpw->id)->count());

        // A button of the shared mail: the decision carries the names of that mail.
        $link = $this->directLinks()['sophie@proximus.example']['approve'];
        $token = substr(parse_url($link, PHP_URL_PATH), strlen('/gpw/key/'), 48);
        $this->post(route('gpw.key.decide', $token), ['decision' => 'approve'])->assertOk();
        $this->assertSame([GpwStatus::Approved, 'Sophie Dubois, Marc Peeters'], [$gpw->fresh()->status, $gpw->fresh()->decided_by_name]);
    }

    #[Test]
    public function interventions_suggest_the_intervention_contacts_of_the_area_and_others_never_do(): void
    {
        Mail::fake();
        $area = WorkSiteArea::factory()->create(['name' => '2-VLA']);
        $areaContact = WorkSiteContact::factory()->create(['first_name' => 'Ann', 'last_name' => 'Area', 'email' => 'ann@proximus.example']);
        $interventionContact = WorkSiteContact::factory()->create(['first_name' => 'Ivo', 'last_name' => 'Interventie', 'email' => 'ivo@proximus.example']);
        $area->contacts()->attach($areaContact);
        $area->interventionContacts()->attach($interventionContact);
        $site = WorkSite::factory()->for($area, 'area')->create(['cow_code' => '06INT']);
        $intervention = Project::factory()->inCategory('600')->for($site)->create(['status' => ProjectStatus::ToBeExecuted, 'work_order_number' => 'WO 777001']);
        $project = Project::factory()->inCategory('610')->for($site)->create(['status' => ProjectStatus::ToBeExecuted]);
        $dates = ['starts_on' => today()->addWeek()->toDateString(), 'ends_on' => today()->addWeek()->addDay()->toDateString()];

        // An intervention: the area's intervention contacts are suggested.
        $this->assertSame([$interventionContact->id], array_keys(RequestGpw::candidateOptions($intervention)));
        $this->assertArrayHasKey($interventionContact->id, RequestGpw::searchRecipients('ivo', $intervention));
        $gpw = app(RequestGpw::class)->handle($intervention, $this->requester($intervention), [...$dates, 'contact_ids' => [$interventionContact->id]]);
        $this->assertSame([$interventionContact->id], $gpw->contact_ids);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('ivo@proximus.example'));

        // Another project: the ordinary contacts; the intervention contact is not offered and not accepted.
        $this->assertSame([$areaContact->id], array_keys(RequestGpw::candidateOptions($project)));
        $this->assertArrayNotHasKey($interventionContact->id, RequestGpw::searchRecipients('ivo', $project));
        $this->expectException(ValidationException::class);
        app(RequestGpw::class)->handle($project, $this->requester($project), [...$dates, 'contact_ids' => [$interventionContact->id]]);
    }

    /**
     * An intervention (600) on three COW codes, with the GPW contact on the first.
     */
    protected function interventionOnThreeSites(): Project
    {
        $project = $this->project();
        $project->update(['work_order_number' => '123456']);
        Project::query()->whereKey($project->id)->update(['project_category_id' => ProjectCategory::query()->where('code', '600')->value('id')]);
        $project = $project->fresh();
        $project->interventionWorkSites()->attach([
            WorkSite::factory()->create(['cow_code' => '03ABC'])->id,
            WorkSite::factory()->create(['cow_code' => '04XYZ'])->id,
        ]);

        return $project->fresh();
    }

    #[Test]
    public function one_request_covers_several_cow_codes_in_one_period_and_one_approval_covers_them_all(): void
    {
        $project = $this->interventionOnThreeSites();
        $requester = $this->requester($project, 'nl', 'jan@power.example');
        $this->actingAs($requester);
        Filament::setCurrentPanel('app');
        Mail::fake();
        $sites = $project->interventionWorkSites->pluck('id', 'cow_code');

        // All COW codes are ticked beforehand, one period.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->mountAction('requestGpw')
            ->assertSchemaStateSet(['work_site_ids' => [$project->work_site_id, $sites['03ABC'], $sites['04XYZ']], 'split' => false]);
        $from = today()->addWeek();
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('requestGpw', [
                'contact_ids' => [$this->contact->id],
                'work_site_ids' => [$project->work_site_id, $sites['04XYZ']],
                'starts_on' => $from->toDateString(),
                'ends_on' => $from->copy()->addDay()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $gpw = GpwRequest::query()->sole();
        $this->assertSame(['02GAM', '04XYZ'], $gpw->sites->pluck('cow_code')->all());
        $this->assertSame('02GAM, 04XYZ', $gpw->cowCodes());
        $this->assertSame($gpw->overallPeriod(), $gpw->period());
        Mail::assertSent(SystemMail::class, 1);
        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => str_contains($mail->rendered['subject'], '02GAM, 04XYZ'));

        // One approval for all of them.
        $this->post(RequestGpw::decisionUrl($gpw), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame(GpwStatus::Approved, $gpw->fresh()->status);
    }

    #[Test]
    public function each_cow_code_can_get_its_own_period_in_the_same_request(): void
    {
        Repeater::fake();
        $project = $this->interventionOnThreeSites();
        $requester = $this->requester($project);
        $this->actingAs($requester);
        Filament::setCurrentPanel('app');
        Mail::fake();
        $sites = $project->interventionWorkSites->pluck('id', 'cow_code');
        $day = fn (int $days): string => today()->addDays($days)->toDateString();

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('requestGpw', [
                'contact_ids' => [$this->contact->id],
                'split' => true,
                'sites' => [
                    ['work_site_id' => $project->work_site_id, 'starts_on' => $day(7), 'ends_on' => $day(7)],
                    ['work_site_id' => $sites['03ABC'], 'starts_on' => $day(8), 'ends_on' => $day(9)],
                    // The same COW code again, on another date.
                    ['work_site_id' => $sites['03ABC'], 'starts_on' => $day(14), 'ends_on' => $day(14)],
                ],
            ])
            ->assertHasNoActionErrors();

        $gpw = GpwRequest::query()->sole();
        $this->assertSame($day(7), $gpw->starts_on->toDateString());
        $this->assertSame($day(14), $gpw->ends_on->toDateString());
        $this->assertCount(3, $gpw->sites);
        $f = fn (int $days): string => today()->addDays($days)->format('d/m/Y');
        $this->assertSame("02GAM: {$f(7)} · 03ABC: {$f(8)} – {$f(9)} · 03ABC: {$f(14)}", $gpw->period());
        $this->assertSame('02GAM, 03ABC', $gpw->cowCodes());
        Mail::assertSent(SystemMail::class, 1);

        // The page behind the mail lists each COW code with its period.
        $this->get(RequestGpw::decisionUrl($gpw))->assertOk()->assertSeeInOrder(['02GAM', $f(7), '03ABC', $f(8), $f(14)]);

        // A COW code of another project, or a period in the past, is refused.
        foreach ([
            ['work_site_id' => WorkSite::factory()->create()->id, 'starts_on' => $day(7), 'ends_on' => $day(7)],
            ['work_site_id' => $sites['04XYZ'], 'starts_on' => $day(-1), 'ends_on' => $day(1)],
            ['work_site_id' => $sites['04XYZ'], 'starts_on' => $day(5), 'ends_on' => $day(4)],
        ] as $row) {
            try {
                app(RequestGpw::class)->handle($project, $requester, ['contact_ids' => [$this->contact->id], 'split' => true, 'sites' => [$row]]);
                $this->fail('An invalid COW code or period was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty(array_filter(array_keys($exception->errors()), fn (string $key): bool => str_starts_with($key, 'sites.0.')));
            }
        }
        $this->assertSame(1, GpwRequest::query()->count());
    }

    #[Test]
    public function a_battery_move_is_requested_for_its_origin_and_destination_at_once(): void
    {
        $origin = WorkSite::factory()->hasAttached($this->contact ??= WorkSiteContact::factory()->create(['email' => 'sophie@proximus.example', 'locale' => 'fr']), [], 'contacts')->create(['cow_code' => 'COW-ORIG']);
        $destination = WorkSite::factory()->create(['cow_code' => 'COW-DEST']);
        $dossier = MoveDossier::factory()->create(['move_from_work_site_id' => $origin->id, 'move_to_work_site_id' => $destination->id]);
        $project = $dossier->project;
        $project->update(['status' => ProjectStatus::ToBeExecuted]);
        $requester = $this->requester($project);
        Mail::fake();

        // The origin's contacts are suggested too.
        $this->assertArrayHasKey($this->contact->id, RequestGpw::candidateOptions($project));

        $gpw = app(RequestGpw::class)->handle($project, $requester, [
            'contact_ids' => [$this->contact->id],
            'split' => true,
            'sites' => [
                ['work_site_id' => $origin->id, 'starts_on' => today()->addWeek()->toDateString(), 'ends_on' => today()->addWeek()->toDateString()],
                ['work_site_id' => $destination->id, 'starts_on' => today()->addWeek()->addDay()->toDateString(), 'ends_on' => today()->addWeek()->addDay()->toDateString()],
            ],
        ]);
        $this->assertSame('COW-ORIG, COW-DEST', $gpw->cowCodes());

        // Added by hand (asked by personal mail): the same choice, also in the past.
        $this->actingAs(User::factory()->admin()->create());
        $manual = GpwRequestResource::addManual(['project_id' => $project->id, 'status' => GpwStatus::Approved->value, 'contact_name' => 'Sophie', 'work_site_ids' => [$origin->id, $destination->id], 'starts_on' => today()->subDay()->toDateString(), 'ends_on' => today()->toDateString()]);
        $this->assertSame(['COW-ORIG', 'COW-DEST'], $manual->sites->pluck('cow_code')->all());
    }

    #[Test]
    public function the_request_names_the_employees_and_a_project_leader_as_contact_person(): void
    {
        Mail::fake();
        $project = $this->project();
        $requester = $this->requester($project, email: 'jan@power.example');
        $anna = User::factory()->create(['name' => 'Anna Leider', 'email' => 'anna@power.example', 'phone' => '0470 11 22 33']);
        $bart = User::factory()->create(['name' => 'Bart Leider', 'email' => 'bart@power.example']);
        $project->leaders()->attach([$anna->id, $bart->id]);
        $piet = User::factory()->create(['name' => 'Piet Technieker']);
        $karel = User::factory()->create(['name' => 'Karel Technieker']);
        $this->actingAs($requester);
        Filament::setCurrentPanel('app');
        $data = [
            'starts_on' => today()->addWeek()->toDateString(),
            'ends_on' => today()->addWeek()->addDays(2)->toDateString(),
            'contact_ids' => [$this->contact->id],
            'employee_ids' => [$piet->id, $karel->id],
        ];

        // Several project leaders: one has to be chosen.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->mountAction('requestGpw')
            ->assertSchemaComponentExists('contact_user_id')
            ->callMountedAction([...$data, 'contact_user_id' => null])
            ->assertHasActionErrors(['contact_user_id' => 'required']);

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('requestGpw', [...$data, 'contact_user_id' => $anna->id])
            ->assertHasNoActionErrors();

        $gpw = GpwRequest::query()->sole();
        $this->assertSame([$piet->id, $karel->id], $gpw->employee_ids);
        $this->assertTrue($gpw->contactUser->is($anna));
        $this->assertSame('Piet Technieker, Karel Technieker', $gpw->employeeNames());

        Mail::assertSent(SystemMail::class, fn (SystemMail $mail): bool => $mail->hasTo('sophie@proximus.example')
            && $mail->hasCc('jan@power.example') && $mail->hasCc('anna@power.example') && ! $mail->hasCc('bart@power.example')
            && str_contains($mail->rendered['html'], 'Piet Technieker, Karel Technieker')
            && str_contains($mail->rendered['html'], 'Personne de contact : Anna Leider') && str_contains($mail->rendered['html'], '0470 11 22 33')
            && ! str_contains($mail->rendered['html'], 'Demandé par'));

        // The decision page shows them too.
        $this->get(RequestGpw::decisionUrl($gpw))->assertOk()->assertSee('Piet Technieker, Karel Technieker')->assertSee('Anna Leider');
    }

    #[Test]
    public function with_one_project_leader_that_leader_is_the_contact_person_without_asking(): void
    {
        $project = $this->project();
        $leader = User::factory()->create();
        $project->leaders()->attach($leader);
        $this->actingAs($this->requester($project));
        Filament::setCurrentPanel('app');

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->mountAction('requestGpw')
            ->assertSchemaComponentHidden('contact_user_id', 'mountedActionSchema0');

        $gpw = $this->gpw($project, $this->requester($project));
        $this->assertTrue($gpw->contactUser->is($leader));

        // Two leaders through the action without a choice is refused.
        $project->leaders()->attach(User::factory()->create());
        $this->expectException(ValidationException::class);
        $this->gpw($project, $this->requester($project));
    }
}
