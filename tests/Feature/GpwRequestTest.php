<?php

namespace Tests\Feature;

use App\Actions\Gpw\PlanGpw;
use App\Actions\Gpw\RequestGpw;
use App\Enums\GpwStatus;
use App\Enums\MailTemplateKey;
use App\Enums\ProjectStatus;
use App\Filament\Client\Resources\GpwRequests\Pages\ListClientGpwRequests;
use App\Filament\Client\Resources\Projects\Pages\ListClientProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Mail\SystemMail;
use App\Models\ClientUser;
use App\Models\GpwRequest;
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
        $site = WorkSite::factory()->create(['cow_code' => '02GAM', 'work_site_contact_id' => $this->contact->id]);

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

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertActionVisible('requestGpw')
            ->callAction('requestGpw', [
                'starts_on' => today()->addWeek()->toDateString(),
                'ends_on' => today()->addWeek()->addDays(2)->toDateString(),
                'remarks' => 'Toegang via achterpoort',
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

        // Not for other statuses, and not without the permission.
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

        $project->workSite->update(['work_site_contact_id' => null]);
        $this->expectException(ValidationException::class);
        $this->gpw($project->fresh(), $requester);
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
        $otherGpw = $this->gpw($other = Project::factory()->inCategory('610')->create(['status' => ProjectStatus::ToBeExecuted, 'work_site_id' => WorkSite::factory()->create(['work_site_contact_id' => WorkSiteContact::factory()->create()->id])->id]), $this->requester($other));

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
        $site = WorkSite::factory()->create(['work_site_contact_id' => null, 'work_site_area_id' => $area->id]);
        $project = Project::factory()->inCategory('610')->for($site)->create(['status' => ProjectStatus::ToBeExecuted]);
        $requester = $this->requester($project, email: 'jan@power.example');

        $gpw = $this->gpw($project, $requester);

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
}
