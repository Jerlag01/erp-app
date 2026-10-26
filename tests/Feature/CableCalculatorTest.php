<?php

namespace Tests\Feature;

use App\Filament\Resources\CableCalculations\Pages\CreateCableCalculation;
use App\Filament\Resources\CableCalculations\Pages\EditCableCalculation;
use App\Filament\Resources\CableCalculations\Pages\ListCableCalculations;
use App\Models\CableCalculation;
use App\Models\User;
use App\Support\CableCalculator;
use App\Support\Modules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CableCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    #[Test]
    public function the_section_meets_the_current_and_the_voltage_drop(): void
    {
        // 32 A three-phase over 50 m: 4 mm² carries it, the voltage drop (3.34 %) asks 6 mm².
        $result = CableCalculator::calculate(['system' => 'ac3', 'voltage' => 400, 'load_current' => 32, 'length' => 50, 'power_factor' => 0.85, 'max_voltage_drop' => 3]);
        $this->assertTrue($result['ok']);
        $this->assertSame([32.0, 32.0, 4.0, 6.0, 6.0, 'voltage_drop'], [$result['ib'], $result['in'], $result['by_current'], $result['by_drop'], $result['section'], $result['governing']]);
        $this->assertSame(2.24, $result['drop_pct']);
        $this->assertSame('3 × 6 mm² Cu XLPE', CableCalculator::label($result['section'], 'cu', 'xlpe', $result['loaded']));

        // 48 V DC, 100 A over 20 m: 70 mm² for 3 %; the short circuit (10 kA, 0.1 s) needs 22.1 mm².
        $dc = CableCalculator::calculate(['system' => 'dc', 'voltage' => 48, 'load_current' => 100, 'length' => 20, 'max_voltage_drop' => 3, 'short_circuit_ka' => 10, 'short_circuit_time' => 0.1]);
        $this->assertSame([2, 70.0, 2.68, 22.1, 25.0], [$dc['loaded'], $dc['section'], $dc['drop_pct'], $dc['s_min_short_circuit'], $dc['by_short_circuit']]);

        // A short circuit that sets the section.
        $short = CableCalculator::calculate(['system' => 'ac3', 'voltage' => 400, 'load_current' => 10, 'length' => 5, 'short_circuit_ka' => 15, 'short_circuit_time' => 0.2]);
        $this->assertSame(['short_circuit', 50.0], [$short['governing'], $short['section']]);
    }

    #[Test]
    public function power_temperature_grouping_and_aluminium_are_taken_into_account(): void
    {
        // 22 kW three-phase at cos φ 0.85: 37.4 A, protected at 40 A.
        $power = CableCalculator::calculate(['load_type' => 'power', 'load_power' => 22, 'length' => 10]);
        $this->assertSame([37.36, 40.0], [$power['ib'], $power['in']]);

        // 40 °C and 3 bunched circuits (B1): k1 0.91 (XLPE), k2 0.70.
        $grouped = CableCalculator::calculate(['load_current' => 40, 'length' => 10, 'installation_method' => 'B1', 'ambient_temperature' => 40, 'grouped_circuits' => 3]);
        $this->assertSame([0.91, 0.7, 10.0], [$grouped['k1'], $grouped['k2'], $grouped['section']]);

        // Aluminium starts at 16 mm².
        $aluminium = CableCalculator::calculate(['load_current' => 20, 'length' => 10, 'material' => 'al']);
        $this->assertSame(16.0, $aluminium['section']);

        // Parallel cables share the current: 630 A over two cables is 315 A each, 120 mm² (Iz 322 A).
        $parallel = CableCalculator::calculate(['load_current' => 600, 'length' => 30, 'parallel_conductors' => 2, 'protective_device' => 630]);
        $this->assertSame([2, 120.0, 644.0], [$parallel['parallel'], $parallel['section'], $parallel['iz']]);

        // Nothing fits, or the input is incomplete.
        $this->assertSame(['no_section'], CableCalculator::calculate(['load_current' => 900, 'length' => 500])['errors']);
        $this->assertSame(['load', 'length'], CableCalculator::calculate(['load_current' => null])['errors']);
        $this->assertSame(['temperature'], CableCalculator::calculate(['load_current' => 10, 'length' => 5, 'insulation' => 'pvc', 'ambient_temperature' => 65])['errors']);
    }

    #[Test]
    public function calculations_are_saved_with_their_result_and_printed_on_the_letterhead(): void
    {
        $user = User::factory()->withPermissions(['cable_calculations' => ['view', 'create', 'update', 'generate_pdf']])->inTeam()->create();
        $this->actingAs($user);

        Livewire::test(CreateCableCalculation::class)
            ->fillForm(['name' => 'Voeding gelijkrichter', 'system' => 'dc', 'voltage' => 48, 'load_current' => 100, 'length' => 20])
            ->assertSee('2 × 70 mm² Cu XLPE')
            ->call('create')
            ->assertHasNoFormErrors();

        $calculation = CableCalculation::sole();
        $this->assertSame($user->id, $calculation->created_by);
        $this->assertSame('2 × 70 mm² Cu XLPE', $calculation->cableLabel());

        // Changing the input changes the saved result.
        Livewire::test(EditCableCalculation::class, ['record' => $calculation->getRouteKey()])
            ->fillForm(['max_voltage_drop' => 5])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(50.0, (float) $calculation->fresh()->result['section']);

        Livewire::test(ListCableCalculations::class)->assertCanSeeTableRecords([$calculation])->assertTableActionVisible('pdf', $calculation);
        $this->get(route('cable-calculations.pdf', $calculation))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Without the PDF right, or with the module off.
        $this->actingAs(User::factory()->withPermissions(['cable_calculations' => ['view']])->inTeam()->create())
            ->get(route('cable-calculations.pdf', $calculation))->assertForbidden();
        settings()->save('modules', [...settings('modules'), Modules::CABLE_CALCULATOR => false]);
        $this->actingAs($user)->get('/cable-calculations')->assertForbidden();
    }
}
