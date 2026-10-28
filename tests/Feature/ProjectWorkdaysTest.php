<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Roles\Pages\EditRole;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use App\Support\ProjectWorkdays;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectWorkdaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
        Filament::setCurrentPanel('app');
    }

    /**
     * A project planned on the given dates (two people on the first day).
     *
     * @param  list<string>  $dates
     */
    protected function plannedProject(?float $estimate, array $dates): Project
    {
        $project = Project::factory()->inCategory('610')->create(['estimated_workdays' => $estimate]);

        foreach ($dates as $index => $date) {
            PlanningItem::factory()->forProject($project)->count($index === 0 ? 2 : 1)->create(['date' => $date]);
        }

        return $project;
    }

    protected function viewer(): User
    {
        // Given to the user alone, on top of an administrator role.
        return User::factory()->admin()->create(['permissions' => ['project_workdays' => ['view']]]);
    }

    #[Test]
    public function the_permission_is_given_per_user_only_never_through_a_role(): void
    {
        $this->assertTrue(Permissions::isUserOnly(ProjectWorkdays::PERMISSION));
        $this->assertFalse(User::factory()->admin()->create()->hasPermission(ProjectWorkdays::PERMISSION));
        $this->assertTrue($this->viewer()->hasPermission(ProjectWorkdays::PERMISSION));

        // A role cannot hold it, not even when sent.
        $role = Role::factory()->create(['permissions' => ['project_workdays' => ['view'], 'projects' => ['view']]]);
        $this->assertSame(['projects' => ['view']], $role->fresh()->permissions);
        $this->assertFalse(User::factory()->create(['role_id' => $role->id])->hasPermission(ProjectWorkdays::PERMISSION));
        $this->assertArrayNotHasKey('project_workdays', Permissions::forRoles());

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');
        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])->assertFormFieldDoesNotExist('permissions.project_workdays');

        // It is ticked on the user (also one with an administrator role).
        $manager = User::factory()->admin()->create();
        Livewire::test(EditUser::class, ['record' => $manager->getRouteKey()])
            ->assertFormFieldVisible('own_permissions.project_workdays')
            ->fillForm(['own_permissions.project_workdays' => ['view']])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($manager->fresh()->hasPermission(ProjectWorkdays::PERMISSION));
    }

    #[Test]
    public function estimated_against_planned_days_turns_red_once_exceeded(): void
    {
        // Three planned days up to today (one with two people), one still to come.
        $over = $this->plannedProject(2, ['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-25']);
        $equal = $this->plannedProject(3, ['2026-11-16', '2026-11-17', '2026-11-18']);
        $noEstimate = $this->plannedProject(null, ['2026-11-16']);

        $this->assertSame(['2/3', 'danger'], [ProjectWorkdays::label($over), ProjectWorkdays::color($over)]);
        $this->assertSame(['3/3', 'success'], [ProjectWorkdays::label($equal), ProjectWorkdays::color($equal)]);
        $this->assertSame(['—/1', 'gray'], [ProjectWorkdays::label($noEstimate), ProjectWorkdays::color($noEstimate)]);

        $this->actingAs($this->viewer());
        Livewire::test(ListProjects::class)
            ->assertTableColumnVisible('workdays')
            ->assertTableColumnStateSet('workdays', '2/3', $over)
            ->filterTable('workdays_over', true)
            ->assertCanSeeTableRecords([$over])
            ->assertCanNotSeeTableRecords([$equal, $noEstimate]);
        $this->get(route('filament.app.resources.projects.view', $over))->assertSee('2/3');

        // Others (also administrators) do not see it.
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListProjects::class)->assertTableColumnHidden('workdays');
        $this->get(route('filament.app.resources.projects.view', $over))->assertDontSee(__('erp.projects.workdays_help'));
    }
}
