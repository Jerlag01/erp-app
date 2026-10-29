<?php

namespace Tests\Feature;

use App\Actions\Gpw\RequestGpw;
use App\Enums\GpwStatus;
use App\Enums\ProjectStatus;
use App\Filament\Resources\GpwRequests\GpwRequestResource;
use App\Filament\Resources\GpwRequests\Pages\ListGpwRequests;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\GpwRequestsRelationManager;
use App\Models\GpwRequest;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteContact;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GpwOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Filament::setCurrentPanel('app');
        $this->team = Team::factory()->create();
    }

    protected function project(ProjectStatus $status = ProjectStatus::ToBeExecuted): Project
    {
        $contact = WorkSiteContact::factory()->create(['email' => fake()->unique()->safeEmail()]);
        $project = Project::factory()->inCategory('610')->for(WorkSite::factory()->create(['work_site_contact_id' => $contact->id]))->create(['status' => $status]);
        $project->teams()->attach($this->team);

        return $project;
    }

    protected function user(): User
    {
        return User::factory()->withPermissions(['projects' => ['view', 'gpw']])->inTeam($this->team)->create();
    }

    #[Test]
    public function every_request_is_listed_with_its_period_and_overview_status(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $waiting = app(RequestGpw::class)->handle($this->project(), $user, ['starts_on' => today()->addWeek()->toDateString(), 'ends_on' => today()->addWeek()->addDays(2)->toDateString()]);
        $approved = GpwRequest::factory()->for($this->project())->create(['status' => GpwStatus::Approved]);
        $rejected = GpwRequest::factory()->for($this->project())->create(['status' => GpwStatus::Rejected]);
        // Of a project the user does not see.
        $hidden = GpwRequest::factory()->create();

        $this->assertSame('Wachten op GPW-goedkeuring', $waiting->overviewStatus());
        $this->assertSame('In te plannen - GPW goedgekeurd', $approved->overviewStatus());
        $this->assertSame('On hold - GPW-aanvraag opnieuw versturen', $rejected->overviewStatus());

        Livewire::test(ListGpwRequests::class)
            ->assertCanSeeTableRecords([$waiting, $approved, $rejected])
            ->assertCanNotSeeTableRecords([$hidden])
            ->assertTableColumnFormattedStateSet('status', 'Wachten op GPW-goedkeuring', $waiting)
            ->assertTableColumnStateSet('starts_on', $waiting->starts_on, $waiting)
            ->set('activeTab', 'rejected')
            ->assertCanSeeTableRecords([$rejected])
            ->assertCanNotSeeTableRecords([$waiting, $approved]);

        // The project list shows the newest request's status.
        Livewire::test(ListProjects::class)->assertTableColumnFormattedStateSet('latestGpwRequest.status', 'In te plannen - GPW goedgekeurd', $approved->project);

        // Only with the GPW permission.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view']])->inTeam($this->team)->create());
        $this->get(GpwRequestResource::getUrl('index'))->assertForbidden();
    }

    #[Test]
    public function a_request_asked_through_a_personal_mail_is_added_by_hand_with_its_status(): void
    {
        $this->actingAs($this->user());
        $project = $this->project();
        $waitingProject = $this->project();

        // Approved: the project becomes "to be planned", without any mail.
        Livewire::test(ListGpwRequests::class)
            ->callAction('create', [
                'project_id' => $project->id,
                'starts_on' => '2026-11-02',
                'ends_on' => '2026-11-04',
                'status' => 'approved',
                'contact_name' => 'Sophie Dubois',
                'decided_by_name' => 'Sophie Dubois',
                'decision_note' => 'Akkoord per mail van 28/10',
            ])
            ->assertHasNoActionErrors();

        $gpw = GpwRequest::query()->where('project_id', $project->id)->sole();
        $this->assertSame([GpwRequest::SOURCE_MANUAL, GpwStatus::Approved, 'Sophie Dubois', null], [$gpw->source, $gpw->status, $gpw->decided_by_name, $gpw->contact_email]);
        $this->assertNotNull($gpw->decided_at);
        $this->assertSame(ProjectStatus::ToBePlanned, $project->fresh()->status);
        Mail::assertNothingSent();

        // Waiting: the decision is recorded later, from the overview.
        Livewire::test(ListGpwRequests::class)
            ->callAction('create', ['project_id' => $waitingProject->id, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'status' => 'pending', 'contact_name' => 'Marc Peeters', 'contact_email' => 'marc@example.com'])
            ->assertHasNoActionErrors();
        $pending = GpwRequest::query()->where('project_id', $waitingProject->id)->sole();
        $this->assertNull($pending->decided_at);
        $this->assertSame(ProjectStatus::ToBeExecuted, $waitingProject->fresh()->status);

        Livewire::test(ListGpwRequests::class)
            ->callAction(TestAction::make('decide')->table($pending), ['decision' => 'rejected', 'decided_by_name' => 'Marc Peeters', 'decision_note' => 'Niet in november'])
            ->assertHasNoActionErrors();
        $this->assertSame(GpwStatus::Rejected, $pending->fresh()->status);
        $this->assertSame('On hold - GPW-aanvraag opnieuw versturen', $pending->fresh()->overviewStatus());

        // The end can not be before the start.
        Livewire::test(ListGpwRequests::class)
            ->callAction('create', ['project_id' => $project->id, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-02', 'status' => 'pending', 'contact_name' => 'X'])
            ->assertHasActionErrors(['ends_on']);
    }

    #[Test]
    public function on_the_project_a_gpw_is_added_by_hand_and_its_requests_are_shown(): void
    {
        $this->actingAs($this->user());
        $project = $this->project();

        // No requests yet: no overview.
        $this->get(route('filament.app.resources.projects.view', $project))->assertOk()->assertDontSee('data-project-gpw', false);

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertActionVisible('addManualGpw')
            ->callAction('addManualGpw', ['starts_on' => '2026-11-16', 'ends_on' => '2026-11-18', 'status' => 'pending', 'contact_name' => 'Sophie Dubois'])
            ->assertHasNoActionErrors();

        $gpw = GpwRequest::query()->where('project_id', $project->id)->sole();
        $this->assertSame(GpwRequest::SOURCE_MANUAL, $gpw->source);

        // The small overview: start, end and status.
        $this->get(route('filament.app.resources.projects.view', $project))
            ->assertSee('data-project-gpw', false)
            ->assertSee('16/11/2026')
            ->assertSee('18/11/2026')
            ->assertSee('Wachten op GPW-goedkeuring');

        // Also from the GPW tab.
        Livewire::test(GpwRequestsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->callTableAction('addManual', data: ['starts_on' => '2026-11-23', 'ends_on' => '2026-11-23', 'status' => 'approved', 'contact_name' => 'Marc'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(2, $project->gpwRequests()->count());
        $this->assertSame(ProjectStatus::ToBePlanned, $project->fresh()->status);

        // Without the GPW permission no button.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view']])->inTeam($this->team)->create());
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])->assertActionHidden('addManualGpw');
    }
}
