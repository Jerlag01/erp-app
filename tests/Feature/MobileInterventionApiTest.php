<?php

namespace Tests\Feature;

use App\Enums\InterventionReportStatus;
use App\Enums\ProjectStatus;
use App\Models\InterventionCategory;
use App\Models\InterventionReport;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileInterventionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function intervention(Team $team): Project
    {
        $project = Project::factory()->inCategory('600')->for(WorkSite::factory()->create(['cow_code' => '02GAM']))->create([
            'work_order_number' => 'WO123456',
            'status' => ProjectStatus::ToBeExecuted,
        ]);
        $project->teams()->attach($team);

        return $project;
    }

    #[Test]
    public function an_employee_closes_an_intervention_in_the_app_without_signing(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->withPermissions(['projects' => ['view']])->inTeam($team)->create();
        $auth = $this->mobileAuth($employee);
        $project = $this->intervention($team);
        $other = Project::factory()->inCategory('600')->create(['work_order_number' => 'WO999999']);
        $connect = InterventionCategory::query()->where('name', 'CONNECT')->sole();

        $this->getJson('/api/v1/me', $auth)->assertJsonPath('data.features.interventions', true);
        $this->getJson('/api/v1/interventions', $auth)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $project->reference)
            ->assertJsonPath('data.0.can_close', true);
        $this->getJson("/api/v1/interventions/{$other->id}", $auth)->assertNotFound();

        $this->getJson("/api/v1/interventions/{$project->id}", $auth)->assertOk()
            ->assertJsonPath('data.values.work_site_id', $project->work_site_id)
            ->assertJsonPath('data.values.work_site_label', '02GAM · '.$project->workSite->city)
            ->assertJsonPath('data.labels.room', __('erp.interventions.room'));
        $this->getJson('/api/v1/interventions/work-sites?search=02G', $auth)->assertOk()->assertJsonPath('data.0.id', $project->work_site_id);

        $this->postJson("/api/v1/interventions/{$project->id}/close", ['categories' => [$connect->id], 'work_site_id' => $project->work_site_id], $auth)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['room', 'hours_worked']);

        $this->postJson("/api/v1/interventions/{$project->id}/close", [
            'categories' => [$connect->id],
            'work_site_id' => $project->work_site_id,
            'room' => 'Technical room 2',
            'hours_worked' => 2.5,
            'materials' => [['description' => 'XVB 3G2.5', 'quantity' => 25, 'direction' => 'used']],
            'positions' => [['board_name' => 'td-01', 'current' => 'DC', 'device' => 'Q', 'number' => '12', 'supply' => 'B']],
            'modified_boards' => ['TD-01'],
        ], $auth)->assertOk()->assertJsonPath('data.can_close', false);

        $report = InterventionReport::sole();
        $this->assertSame(InterventionReportStatus::Pending, $report->status);
        $this->assertSame($employee->id, $report->closed_by);
        $this->assertSame('2:30', $report->hoursLabel());
        $this->assertSame(ProjectStatus::Executed, $project->fresh()->status);

        // Once closed, not again until it is sent back.
        $this->postJson("/api/v1/interventions/{$project->id}/close", ['categories' => [$connect->id], 'work_site_id' => $project->work_site_id, 'room' => 'x', 'hours_worked' => 1], $auth)->assertForbidden();
        $report->update(['status' => InterventionReportStatus::Rejected, 'review_note' => 'Foto ontbreekt']);
        $this->getJson("/api/v1/interventions/{$project->id}", $auth)->assertJsonPath('data.can_close', true)
            ->assertJsonPath('data.review_note', 'Foto ontbreekt')
            ->assertJsonPath('data.values.room', 'Technical room 2');
    }

    #[Test]
    public function the_app_fills_in_a_form_per_cow_code(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->withPermissions(['projects' => ['view']])->inTeam($team)->create();
        $auth = $this->mobileAuth($employee);
        $project = $this->intervention($team);
        $extra = WorkSite::factory()->create(['cow_code' => '02NO1', 'city' => 'Zaventem']);
        $project->interventionWorkSites()->attach($extra);
        $connect = InterventionCategory::query()->where('name', 'CONNECT')->sole();
        $close = fn (int $siteId): array => ['categories' => [$connect->id], 'work_site_id' => $siteId, 'room' => 'Room '.$siteId, 'hours_worked' => 1];

        $this->getJson('/api/v1/interventions', $auth)->assertOk()
            ->assertJsonPath('data.0.cow_code', '02GAM, 02NO1')
            ->assertJsonPath('data.0.report_status_label', __('erp.interventions.sites_closed', ['closed' => 0, 'total' => 2]));

        $this->getJson("/api/v1/interventions/{$project->id}", $auth)->assertOk()
            ->assertJsonPath('data.several_sites', true)
            ->assertJsonCount(2, 'data.sites')
            ->assertJsonPath('data.site_id', $project->work_site_id)
            ->assertJsonPath('data.values.work_site_id', $project->work_site_id);

        $this->postJson("/api/v1/interventions/{$project->id}/close", $close($project->work_site_id), $auth)->assertOk()->assertJsonPath('data.can_close', true);
        $this->assertSame(ProjectStatus::ToBeExecuted, $project->fresh()->status);

        // The closed COW code shows read-only, the open one is proposed next.
        $this->getJson("/api/v1/interventions/{$project->id}?site={$project->work_site_id}", $auth)->assertJsonPath('data.values', null)
            ->assertJsonPath('data.sites.0.can_close', false);
        $this->getJson("/api/v1/interventions/{$project->id}", $auth)->assertJsonPath('data.site_id', $extra->id)
            ->assertJsonPath('data.values.work_site_id', $extra->id);

        $this->postJson("/api/v1/interventions/{$project->id}/close", $close($project->work_site_id), $auth)->assertUnprocessable()->assertJsonValidationErrors(['work_site_id']);
        $this->postJson("/api/v1/interventions/{$project->id}/close", $close($extra->id), $auth)->assertOk()->assertJsonPath('data.can_close', false);

        $this->assertSame(['02GAM', '02NO1'], InterventionReport::query()->orderBy('id')->pluck('cow_code')->all());
        $this->assertSame(ProjectStatus::Executed, $project->fresh()->status);
    }
}
