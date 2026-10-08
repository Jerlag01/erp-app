<?php

namespace Tests\Feature;

use App\Filament\App\Widgets\MyAssets;
use App\Filament\App\Widgets\MyTeams;
use App\Filament\App\Widgets\MyVehicles;
use App\Filament\App\Widgets\VacationBalance;
use App\Filament\Resources\OrderReferences\Widgets\OrderReferenceCounter;
use App\Filament\Widgets\ControlsDue;
use App\Filament\Widgets\ErpStats;
use App\Filament\Widgets\InspectionsDue;
use App\Filament\Widgets\OpenDamages;
use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\Team;
use App\Models\User;
use App\Models\VacationRequest;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WidgetsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_due_lists_show_overdue_and_upcoming_items_only(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        $overdue = Vehicle::factory()->controlDueIn(-3)->create();
        $soon = Vehicle::factory()->controlDueIn(10)->create();
        $later = Vehicle::factory()->controlDueIn(200)->create();

        Livewire::test(ControlsDue::class)
            ->assertCanSeeTableRecords([$overdue, $soon])
            ->assertCanNotSeeTableRecords([$later]);

        $dueAsset = Asset::factory()->create(['next_inspection_date' => today()->addDays(5)]);
        $fineAsset = Asset::factory()->create(['next_inspection_date' => today()->addYear()]);

        Livewire::test(InspectionsDue::class)
            ->assertCanSeeTableRecords([$dueAsset])
            ->assertCanNotSeeTableRecords([$fineAsset]);

        $open = AssetLog::factory()->damage()->create();
        $resolved = AssetLog::factory()->damage()->create(['resolved_at' => now()]);

        Livewire::test(OpenDamages::class)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$resolved]);

        Livewire::test(ErpStats::class)->assertOk();
    }

    #[Test]
    public function the_widgets_follow_modules_and_permissions(): void
    {
        $this->actingAs(User::factory()->withPermissions(['assets' => ['view']])->inTeam()->create());

        $this->assertTrue(InspectionsDue::canView());
        $this->assertFalse(ControlsDue::canView());
        $this->assertFalse(OpenDamages::canView());

        $this->setModules(['assets' => false]);

        $this->assertFalse(InspectionsDue::canView());
        $this->assertFalse(ErpStats::canView());
    }

    #[Test]
    public function the_personal_widgets_render(): void
    {
        $employee = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($employee);
        $vehicle = Vehicle::factory()->for($team)->create();
        $this->actingAs($employee);
        Filament::setCurrentPanel('app');

        Livewire::test(MyTeams::class)->assertCanSeeTableRecords([$team]);
        Livewire::test(MyVehicles::class)->assertCanSeeTableRecords([$vehicle]);

        $this->setModules(['teams' => false]);

        $this->assertFalse(MyTeams::canView());
        $this->assertFalse(MyVehicles::canView());
        $this->assertFalse(MyAssets::canView());
    }

    #[Test]
    public function the_vacation_balance_and_order_counter_widgets_render(): void
    {
        $user = User::factory()->admin()->create(['name' => 'Jeroen Lagaet']);
        VacationRequest::factory()->for($user)->approved()->create(['start_date' => now()->startOfYear()->addDays(40), 'end_date' => now()->startOfYear()->addDays(40)]);
        $this->actingAs($user);
        Filament::setCurrentPanel('app');

        Livewire::test(VacationBalance::class)->assertOk()->assertSee('20');
        Livewire::test(OrderReferenceCounter::class)->assertOk()->assertSee('/JL');
    }
}
