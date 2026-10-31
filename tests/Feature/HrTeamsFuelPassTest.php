<?php

namespace Tests\Feature;

use App\Filament\Pages\HrAdvances;
use App\Filament\Pages\Planning;
use App\Filament\Pages\Timesheet;
use App\Filament\Pages\TimesheetOverview;
use App\Filament\Resources\FuelPasses\FuelPassResource;
use App\Filament\Resources\FuelPasses\Pages\ManageFuelPasses;
use App\Models\Employee;
use App\Models\FuelPass;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\TimesheetReport;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrTeamsFuelPassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    protected function hr(): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('name', 'HR')->sole()->id, 'name' => 'Hilde HR']);
    }

    #[Test]
    public function the_hr_role_sees_the_monthly_prestations_and_hr_calculations_without_a_sheet_of_its_own(): void
    {
        $role = Role::query()->where('name', 'HR')->sole();
        $this->assertTrue($role->no_timesheet);
        $hr = $this->hr();
        $worker = User::factory()->create(['name' => 'Jan Arbeider']);
        Employee::factory()->for($worker)->create();
        $this->actingAs($hr);

        $this->assertFalse($hr->hasTimesheet());
        $this->assertTrue(TimesheetOverview::canAccess());
        $this->assertTrue(HrAdvances::canAccess());

        // No sheet of their own: the prestations page opens on an employee's.
        $this->assertNotSame($hr->id, Livewire::test(Timesheet::class)->instance()->employeeUser()->id);

        // Not in the planning, the prestation lists, vacation or sickness.
        $this->assertFalse(Planning::canAccess());
        $this->assertTrue(TimesheetOverview::canAccess());
        $this->get('/my-vacation')->assertForbidden();
        $this->get('/my-absences')->assertForbidden();
        $ids = app(TimesheetReport::class)->employees(CarbonImmutable::parse('first day of this month'), CarbonImmutable::parse('last day of this month'))->pluck('id');
        $this->assertNotContains($hr->id, $ids);
        $this->assertContains($worker->id, $ids);
        $this->getJson('/api/v1/me', $this->mobileAuth($hr))->assertJsonPath('data.features.timesheets', false)->assertJsonPath('data.features.leave', false)->assertJsonPath('data.features.planning', false);

        // A planner does not get them in the planning.
        $this->actingAs(User::factory()->admin()->create());
        $this->assertArrayNotHasKey($hr->id, Livewire::test(Planning::class)->instance()->personOptions());
    }

    #[Test]
    public function the_administration_role_works_like_hr_without_a_team(): void
    {
        $role = Role::query()->where('name', 'Administratie')->sole();
        $this->assertTrue($role->no_timesheet);
        $this->assertFalse($role->is_admin);
        // By default the monthly prestations, and all leave and absences of everyone.
        $this->assertSame(['timesheets' => ['see_all', 'generate_pdf'], 'vacations' => ['view', 'approve', 'manage']], $role->permissions);

        // Other modules come from the role's permissions, also without a team.
        $role->update(['permissions' => [...$role->permissions, 'invoices' => ['view']]]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->assertSame([], $user->teamIds());
        $this->assertFalse($user->isGuest());
        $this->assertTrue($user->hasPermission('invoices.view'));

        // No prestation sheet, planning or leave.
        $this->actingAs($user);
        $this->assertFalse($user->hasTimesheet());
        $this->assertFalse(Planning::canAccess());
        $this->assertTrue(TimesheetOverview::canAccess());
        $this->get('/my-vacation')->assertForbidden();
        $this->getJson('/api/v1/me', $this->mobileAuth($user))->assertJsonPath('data.features.timesheets', false)->assertJsonPath('data.features.planning', false);
    }

    #[Test]
    public function teams_are_labelled_with_the_first_names_of_their_members(): void
    {
        $team = Team::factory()->create(['name' => 'Ploeg Gent']);
        $jan = User::factory()->inTeam($team)->create(['name' => 'Jan Peeters']);
        $an = User::factory()->inTeam($team)->create(['name' => 'Account An']);
        Employee::factory()->for($an)->create(['first_name' => 'An']);
        User::factory()->inTeam($team)->create(['name' => 'Oud Lid', 'is_active' => false]);

        $this->assertSame('Ploeg Gent - An / Jan', $team->fresh()->label());
        $this->assertSame([$team->id => 'Ploeg Gent - An / Jan'], Team::options());
        $this->assertSame('Leeg', Team::factory()->create(['name' => 'Leeg'])->label());
        $this->assertSame('Jan', $jan->firstName());
    }

    #[Test]
    public function fuel_pass_pins_are_only_shown_to_the_team_and_with_the_permission(): void
    {
        $team = Team::factory()->create();
        $vehicle = Vehicle::factory()->create(['team_id' => $team->id, 'plate_number' => '1ABC123']);
        // An administrator sees every vehicle, but no PIN unless "view_pin" is ticked for them.
        $manager = User::factory()->admin()->create();
        $this->actingAs($manager);

        Livewire::test(ManageFuelPasses::class)
            ->callAction('create', ['vehicle_id' => $vehicle->id, 'card_number' => '7077 1234 5678 9012 345', 'pin_code' => '123', 'expires_on' => '13/2028'])
            ->assertHasActionErrors(['card_number', 'pin_code', 'expires_on']);
        Livewire::test(ManageFuelPasses::class)
            ->callAction('create', ['vehicle_id' => $vehicle->id, 'card_number' => '7077 1234 5678 9012 34', 'pin_code' => '4821', 'expires_on' => '08/2028'])
            ->assertHasNoActionErrors();

        $pass = FuelPass::sole();
        $this->assertSame('707712345678901234', $pass->card_number);
        $this->assertSame('4821', $pass->pin_code);
        // Month and year: valid until the end of that month.
        $this->assertSame(['2028-08-31', '08/2028', false], [$pass->expires_on->toDateString(), $pass->expiryLabel(), $pass->isExpired()]);
        $this->assertTrue(FuelPass::factory()->make(['expires_on' => now()->subMonthNoOverflow()->endOfMonth()])->isExpired());
        $this->assertStringNotContainsString('4821', (string) \DB::table('fuel_passes')->value('pin_code'));
        $this->assertArrayNotHasKey('pin_code', $pass->toArray());

        // The administrator sees the pass, but not the PIN.
        Livewire::test(ManageFuelPasses::class)->assertCanSeeTableRecords([$pass])->assertDontSee('4821')->assertActionHidden(TestAction::make('showPin')->table($pass));
        $this->assertFalse($pass->pinVisibleTo(User::factory()->admin()->create()));

        // A member of the vehicle's team sees their pass and its PIN, without the permission.
        $member = User::factory()->inTeam($team)->create();
        $this->actingAs($member);
        Livewire::test(ManageFuelPasses::class)
            ->assertCanSeeTableRecords([$pass])
            ->mountAction(TestAction::make('showPin')->table($pass))
            ->assertMountedActionModalSee('4821');

        // Someone of another team sees nothing.
        $this->actingAs(User::factory()->inTeam()->create());
        $this->get(FuelPassResource::getUrl('index'))->assertForbidden();

        // "view_pin" (explicit) shows every PIN.
        $this->assertTrue($pass->pinVisibleTo(User::factory()->admin()->create(['permissions' => ['fuel_passes' => ['view_pin']]])));
    }
}
