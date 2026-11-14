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
    public function dc_follows_the_48_volt_calculation_note(): void
    {
        // The four circuits of the calculation note: S = Imax × L × 2 × 0.0184 / ΔU, and S ÷ the provided section.
        foreach ([
            [40, 13, 4, 10, 4.78, 6.0, 0.48],      // ALSB – Krachtbord, breaker 40 A
            [160, 8, 0.7, 95, 67.29, 70.0, 0.71],  // Krachtbord – Batterij 1, fuses 160 A T03
            [160, 6, 1.3, 70, 27.18, 35.0, 0.39],  // Krachtbord – Muurbord A F301
        ] as [$imax, $length, $drop, $provided, $required, $suggested, $ratio]) {
            $result = CableCalculator::calculate(['system' => 'dc', 'voltage' => 48, 'protective_device' => $imax, 'length' => $length, 'resistivity' => 0.0184, 'max_drop_volts' => $drop, 'chosen_section' => $provided]);
            $this->assertTrue($result['ok']);
            $this->assertSame(['dc', $required, $suggested, (float) $provided, $ratio], [$result['mode'], $result['s_required'], $result['suggested'], $result['section'], $result['ratio']]);
        }

        // Without a provided section: the next standard one. Two cables per pole share the section.
        $auto = CableCalculator::calculate(['system' => 'dc', 'protective_device' => 160, 'length' => 8, 'max_drop_volts' => 0.7]);
        $this->assertSame([70.0, 0.96, 0.67, 1.4], [$auto['section'], $auto['ratio'], $auto['drop_v'], $auto['drop_pct']]);
        $parallel = CableCalculator::calculate(['system' => 'dc', 'protective_device' => 160, 'length' => 8, 'max_drop_volts' => 0.7, 'parallel_conductors' => 2]);
        $this->assertSame([67.29, 33.65, 35.0], [$parallel['s_total'], $parallel['s_required'], $parallel['section']]);
        $this->assertSame('2 × 35 mm² per pool', CableCalculator::dcLabel($parallel['section'], 2));

        // A provided section that is too small, or missing input.
        $small = CableCalculator::calculate(['system' => 'dc', 'protective_device' => 160, 'length' => 8, 'max_drop_volts' => 0.7, 'chosen_section' => 50]);
        $this->assertSame([false, ['section_too_small'], 1.35], [$small['ok'], $small['errors'], $small['ratio']]);
        $this->assertSame(['device', 'length'], CableCalculator::calculate(['system' => 'dc'])['errors']);
    }

    #[Test]
    public function calculations_are_saved_with_their_result_and_printed_on_the_letterhead(): void
    {
        $user = User::factory()->withPermissions(['cable_calculations' => ['view', 'create', 'update', 'generate_pdf']])->inTeam()->create();
        $this->actingAs($user);

        // AC or DC first: the DC fields only appear for DC.
        Livewire::test(CreateCableCalculation::class)
            ->assertFormFieldHidden('max_drop_volts')
            ->assertFormFieldHidden('load_current')
            ->fillForm(['name' => 'Krachtbord - Batterij 1', 'kind' => 'dc'])
            ->assertFormFieldVisible('max_drop_volts')
            ->assertFormFieldHidden('load_current')
            ->fillForm(['protection_type' => 'fuse', 'protection_size' => 'T03', 'protective_device' => 160, 'length' => 8, 'max_drop_volts' => 0.7, 'chosen_section' => '95'])
            ->assertSee('95 mm² per pool')
            ->call('create')
            ->assertHasNoFormErrors();

        $calculation = CableCalculation::sole();
        $this->assertSame($user->id, $calculation->created_by);
        $this->assertSame(['dc', 'fuse', '0.01840'], [$calculation->system, $calculation->protection_type, $calculation->resistivity]);
        $this->assertSame('95 mm² per pool', $calculation->cableLabel());
        $this->assertSame(0.71, $calculation->result['ratio']);

        // Changing the input changes the saved result.
        Livewire::test(EditCableCalculation::class, ['record' => $calculation->getRouteKey()])
            ->assertFormSet(['kind' => 'dc'])
            ->fillForm(['chosen_section' => null, 'max_drop_volts' => 1.3])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(50.0, (float) $calculation->fresh()->result['section']);

        // AC keeps the IEC calculation.
        Livewire::test(CreateCableCalculation::class)
            ->fillForm(['name' => 'Voeding TD-01', 'kind' => 'ac'])
            ->assertFormSet(['system' => 'ac3', 'voltage' => 400])
            ->fillForm(['load_current' => 32, 'length' => 50])
            ->assertSee('3 × 6 mm² Cu XLPE')
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame('3 × 6 mm² Cu XLPE', CableCalculation::query()->where('name', 'Voeding TD-01')->sole()->cableLabel());

        Livewire::test(ListCableCalculations::class)->assertCanSeeTableRecords([$calculation])->assertTableActionVisible('pdf', $calculation);
        $this->get(route('cable-calculations.pdf', $calculation))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Without the PDF right, or with the module off.
        $this->actingAs(User::factory()->withPermissions(['cable_calculations' => ['view']])->inTeam()->create())
            ->get(route('cable-calculations.pdf', $calculation))->assertForbidden();
        settings()->save('modules', [...settings('modules'), Modules::CABLE_CALCULATOR => false]);
        $this->actingAs($user)->get('/cable-calculations')->assertForbidden();
    }
}
