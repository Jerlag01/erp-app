<?php

namespace Tests\Feature;

use App\Actions\Gpw\PlanGpw;
use App\Actions\Gpw\RequestGpw;
use App\Enums\GpwStatus;
use App\Enums\MailTemplateKey;
use App\Enums\ProjectStatus;
use App\Filament\Client\Resources\GpwRequests\Pages\ListClientGpwRequests;
use App\Filament\Client\Resources\Projects\Pages\ListClientProjects;
use App\Filament\Resources\GpwRequests\GpwRequestResource;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Mail\SystemMail;
use App\Models\ClientUser;
use App\Models\GpwRequest;
use App\Models\GpwRequestKey;
use App\Models\MailTemplate;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteArea;
use App\Models\WorkSiteContact;
use App\Support\ClientPortal;
use Filament\Facades\Filament;
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

        // "Approve" without logging in: the page sends it by itself (in the recipient's language).
        $page = $this->get($links['marc@proximus.example']['approve'])->assertOk()->assertSee('id="gpw-approve"', escape: false)->assertSee('Marc Peeters');
        $token = substr(parse_url($links['marc@proximus.example']['approve'], PHP_URL_PATH), strlen('/gpw/key/'), 48);
        $this->assertSame(GpwStatus::Pending, $gpw->fresh()->status, 'Opening the link alone sends nothing yet.');
        $this->post(route('gpw.key.decide', $token), ['decision' => 'approve'])->assertOk()->assertSee(GpwStatus::Approved->getLabel());

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
}
