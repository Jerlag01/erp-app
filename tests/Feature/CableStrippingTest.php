<?php

namespace Tests\Feature;

use App\Filament\Resources\CableStripping\Pages\ManageCableStrippingLines;
use App\Models\CableStrippingLine;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CableStrippingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    protected function user(array $abilities): User
    {
        return User::factory()->withPermissions(['cable_stripping' => $abilities])->inTeam()->create(['name' => 'John']);
    }

    #[Test]
    public function the_calculations_follow_the_excel(): void
    {
        // First line of "Kabel ontmantelen.xlsm".
        $line = CableStrippingLine::factory()->create([
            'imputation' => '02IXE', 'start_time' => '13:00', 'end_time' => '14:45',
            'weight_before' => 367, 'empty_bin_weight' => 703, 'weight_after' => 972, 'bin_number' => 'PI06',
        ]);

        $this->assertSame(1.75, $line->hours());
        $this->assertSame(-336.0, $line->cableBefore());
        $this->assertSame(269.0, $line->strippedCable());
        $this->assertSame(-605.0, $line->waste());
    }

    #[Test]
    public function everyone_with_access_adds_lines_but_only_the_right_removes_and_exports(): void
    {
        $worker = $this->user(['view', 'create', 'update']);
        $colleague = User::factory()->inTeam()->create(['name' => 'Senne']);
        $this->actingAs($worker);

        $this->get('/cable-stripping')->assertOk();

        Livewire::test(ManageCableStrippingLines::class)
            ->assertActionHidden('pdf')
            ->callAction('create', [
                'date' => '2026-10-16', 'imputation' => '59OOS', 'workers' => [$worker->id, $colleague->id],
                'start_time' => '06:30', 'end_time' => '11:30', 'weight_before' => 1067, 'empty_bin_weight' => 38, 'weight_after' => 923, 'bin_number' => 'PI26',
            ])
            ->assertHasNoActionErrors();

        $line = CableStrippingLine::sole();
        $this->assertSame(['John', 'Senne'], $line->workers()->orderBy('name')->pluck('name')->all());
        $this->assertSame([5.0, 1029.0, 885.0, 144.0], [$line->hours(), $line->cableBefore(), $line->strippedCable(), $line->waste()]);
        $this->assertSame($worker->id, $line->created_by);

        Livewire::test(ManageCableStrippingLines::class)
            ->assertCanSeeTableRecords([$line])
            ->assertTableActionHidden('delete', $line)
            ->assertSee('885,00');

        // With the rights: remove lines and export the PDF.
        $this->actingAs($this->user(['view', 'delete', 'generate_pdf']));
        Livewire::test(ManageCableStrippingLines::class)
            ->filterTable('period', ['from' => '2026-10-01', 'until' => '2026-10-31'])
            ->callAction('pdf')
            ->assertFileDownloaded('kabel-ontmantelen-'.now()->format('Y-m-d').'.pdf');

        $html = view('cable-stripping.pdf', ['lines' => CableStrippingLine::query()->with('workers')->get(), 'from' => '2026-10-01', 'until' => null, 'totals' => ['hours' => 5, 'weight_before' => 1067, 'weight_after' => 923, 'cable_before' => 1029, 'waste' => 144, 'stripped' => 885]])->render();
        $this->assertStringContainsString('Totaal KG ontmantelde kabel', $html);
        $this->assertStringContainsString('John, Senne', $html);

        Livewire::test(ManageCableStrippingLines::class)->callAction(TestAction::make('delete')->table($line));
        $this->assertSame(0, CableStrippingLine::query()->count());
        $this->assertDatabaseCount('cable_stripping_line_user', 0);
    }

    #[Test]
    public function the_module_can_be_switched_off(): void
    {
        $this->actingAs($this->user(['view', 'create']));
        $this->setModules(['cable_stripping' => false]);

        $this->get('/cable-stripping')->assertForbidden();
    }
}
