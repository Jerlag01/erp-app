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
use Filament\Forms\Components\Repeater;
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
        // 3F with the earth in the cable: four conductors.
        $this->assertSame('4G6 mm² Cu XLPE', CableCalculator::acLabel($result, 'cu', 'xlpe'));

        // A short circuit that sets the section.
        $short = CableCalculator::calculate(['system' => 'ac3', 'voltage' => 400, 'load_current' => 10, 'length' => 5, 'short_circuit_ka' => 15, 'short_circuit_time' => 0.2]);
        $this->assertSame(['short_circuit', 50.0], [$short['governing'], $short['section']]);
    }

    #[Test]
    public function the_net_earthing_frequency_and_maximum_section_shape_the_cable(): void
    {
        $base = ['load_current' => 32, 'length' => 50];

        // 3F+N with the earth in the cable (default, ΔU max 4 %): 5 conductors; the earth apart: + PE.
        $default = CableCalculator::calculate($base);
        $this->assertSame(['ac3n', 4.0, '5G4 mm² Cu XLPE'], [$default['system'], $default['section'], CableCalculator::acLabel($default, 'cu', 'xlpe')]);
        $apart = CableCalculator::calculate([...$base, 'load_current' => 250, 'earth_in_cable' => false]);
        $this->assertSame([95.0, 50.0, '4 × 95 mm² + PE 50 mm² Cu XLPE'], [$apart['section'], $apart['pe_section'], CableCalculator::acLabel($apart, 'cu', 'xlpe')]);

        // 2F (phase to phase, 230 V) and 1F+N: two loaded conductors, factor 2 for the voltage drop.
        $twoPhase = CableCalculator::calculate([...$base, 'system' => 'ac2', 'voltage' => 230]);
        $this->assertSame([2, '3G10 mm² Cu XLPE'], [$twoPhase['loaded'], CableCalculator::acLabel($twoPhase, 'cu', 'xlpe')]);
        $single = CableCalculator::calculate([...$base, 'system' => 'ac1n', 'voltage' => 230, 'load_type' => 'apparent', 'load_kva' => 5]);
        $this->assertSame([21.74, '3G6 mm² Cu XLPE'], [$single['ib'], CableCalculator::acLabel($single, 'cu', 'xlpe')]);

        // kVA three-phase: S ÷ (√3 × U).
        $this->assertSame(36.08, CableCalculator::calculate([...$base, 'load_type' => 'apparent', 'load_kva' => 25])['ib']);

        // TN-C: PEN in the cable, at least 10 mm² copper.
        $tnc = CableCalculator::calculate(['load_current' => 10, 'length' => 5, 'earthing' => 'tnc']);
        $this->assertSame([10.0, 'pen', '4 × 10 mm² (PEN) Cu XLPE'], [$tnc['section'], $tnc['governing'], CableCalculator::acLabel($tnc, 'cu', 'xlpe')]);

        // 60 Hz: a larger reactance, so a larger drop.
        $this->assertGreaterThan(CableCalculator::calculate([...$base, 'load_current' => 200])['drop_pct'], CableCalculator::calculate([...$base, 'load_current' => 200, 'frequency' => 60])['drop_pct']);

        // At most 95 mm²: 400 A takes two cables in parallel (200 A each: 70 mm²).
        $limited = CableCalculator::calculate(['load_current' => 400, 'length' => 30, 'max_section' => 95]);
        $this->assertSame([true, 2, 70.0, '2 × (5G70 mm²) Cu XLPE'], [$limited['parallel_auto'], $limited['parallel'], $limited['section'], CableCalculator::acLabel($limited, 'cu', 'xlpe')]);
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
        // 900 A over 500 m: cables are added in parallel; with ten cables still not enough: no section.
        $this->assertTrue(CableCalculator::calculate(['load_current' => 900, 'length' => 500])['parallel_auto']);
        $this->assertSame(['no_section'], CableCalculator::calculate(['load_current' => 5000, 'length' => 5000])['errors']);
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

        // Several circuits at once, AC or DC each, from a start point to an end point.
        $undo = Repeater::fake();
        Livewire::test(CreateCableCalculation::class)
            ->fillForm(['name' => 'Berekeningsnota 02NO1', 'circuits' => [['from_point' => 'Krachtbord', 'to_point' => 'Batterij 1']]])
            ->assertFormFieldHidden('circuits.0.max_drop_volts')
            ->assertFormFieldHidden('circuits.0.load_current')
            ->fillForm(['circuits' => [['from_point' => 'Krachtbord', 'to_point' => 'Batterij 1', 'kind' => 'dc']]])
            ->assertFormFieldVisible('circuits.0.max_drop_volts')
            ->assertFormFieldHidden('circuits.0.load_current')
            ->fillForm(['circuits' => [
                ['from_point' => 'ALSB', 'to_point' => 'Krachtbord', 'kind' => 'ac', 'system' => 'ac3', 'voltage' => 400, 'earthing' => 'tns', 'frequency' => 50, 'earth_in_cable' => true, 'load_type' => 'current', 'load_current' => 32, 'length' => 50, 'power_factor' => 0.85,
                    'material' => 'cu', 'insulation' => 'xlpe', 'installation_method' => 'C', 'ambient_temperature' => 30, 'grouped_circuits' => 1, 'parallel_conductors' => 1, 'max_voltage_drop' => 3],
                ['from_point' => 'Krachtbord', 'to_point' => 'Batterij 1', 'kind' => 'dc', 'system' => 'dc', 'voltage' => 48, 'protection_type' => 'fuse', 'protection_size' => 'T03', 'protective_device' => 160,
                    'length' => 8, 'resistivity' => 0.0184, 'max_drop_volts' => 0.7, 'parallel_conductors' => 1, 'chosen_section' => '95'],
                ['from_point' => 'Krachtbord', 'to_point' => 'Muurbord A F301', 'kind' => 'dc', 'system' => 'dc', 'voltage' => 48, 'protection_type' => 'fuse', 'protective_device' => 160,
                    'length' => 6, 'resistivity' => 0.0184, 'max_drop_volts' => 1.3, 'parallel_conductors' => 1, 'chosen_section' => '25'],
            ]])
            ->assertSee('4G6 mm² Cu XLPE')
            ->assertSee('95 mm² per pool')
            ->call('create')
            ->assertHasNoFormErrors();
        $undo();

        $calculation = CableCalculation::sole();
        $circuits = $calculation->circuits;
        $this->assertSame($user->id, $calculation->created_by);
        $this->assertSame(['ALSB → Krachtbord', 'Krachtbord → Batterij 1', 'Krachtbord → Muurbord A F301'], $circuits->map->route()->all());
        $this->assertSame(['4G6 mm² Cu XLPE', '95 mm² per pool', '25 mm² per pool'], $circuits->map->cableLabel()->all());
        $this->assertSame(['ac3', 'dc', 'dc'], $circuits->pluck('system')->all());
        $this->assertSame(0.71, $circuits[1]->result['ratio']);
        // 27.18 mm² needed: 25 mm² provided is too small.
        $this->assertSame(1, $calculation->failingCircuits());

        // Changing a circuit changes its saved result.
        $circuits[2]->update(['chosen_section' => 35]);
        $this->assertSame(0, $calculation->fresh()->failingCircuits());

        Livewire::test(EditCableCalculation::class, ['record' => $calculation->getRouteKey()])
            ->assertSee('Krachtbord → Muurbord A F301')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(3, $calculation->circuits()->count());

        // A copy takes all circuits.
        Livewire::test(ListCableCalculations::class)->callTableAction('replicate', $calculation);
        $copy = CableCalculation::query()->whereKeyNot($calculation->id)->sole();
        $this->assertSame(['4G6 mm² Cu XLPE', '95 mm² per pool', '35 mm² per pool'], $copy->circuits->map->cableLabel()->all());

        Livewire::test(ListCableCalculations::class)->assertCanSeeTableRecords([$calculation])->assertTableActionVisible('pdf', $calculation);
        $this->get(route('cable-calculations.pdf', $calculation))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Without the PDF right, or with the module off.
        $this->actingAs(User::factory()->withPermissions(['cable_calculations' => ['view']])->inTeam()->create())
            ->get(route('cable-calculations.pdf', $calculation))->assertForbidden();
        settings()->save('modules', [...settings('modules'), Modules::CABLE_CALCULATOR => false]);
        $this->actingAs($user)->get('/cable-calculations')->assertForbidden();
    }
}
