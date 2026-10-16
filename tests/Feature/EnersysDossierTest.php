<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Filament\Resources\EnersysDossiers\Pages\CreateEnersysDossier;
use App\Filament\Resources\EnersysDossiers\Pages\EditEnersysDossier;
use App\Models\BatteryDossier;
use App\Models\EnersysDossier;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnersysDossierTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Filament::setCurrentPanel('app');
        $this->team = Team::factory()->create();
    }

    protected function technician(array $enersys = ['view', 'create', 'update', 'delete', 'input', 'prices', 'generate_pdf']): User
    {
        return User::factory()->withPermissions(['projects' => ['view'], 'enersys' => $enersys])->inTeam($this->team)->create();
    }

    protected function project(): Project
    {
        $project = Project::factory()->inCategory('605')->for(WorkSite::factory()->create(['cow_code' => '03WOM', 'street' => 'Jacobsveldweg', 'house_number' => '15', 'postal_code' => '2160', 'city' => 'WOMMELGEM']))->create();
        $project->teams()->attach($this->team);

        return $project;
    }

    protected function signature(): string
    {
        return 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    #[Test]
    public function an_enersys_dossier_is_created_on_a_605_project_like_the_excel(): void
    {
        $this->actingAs($this->technician());
        $project = $this->project();

        Livewire::test(CreateEnersysDossier::class)
            ->fillForm([
                'project_id' => $project->id, 'contact_person' => 'Pascal Callebaut', 'po_number' => 'NVT',
                'old_capacity_ah' => 0, 'old_count' => 2, 'removal' => true,
                'new_capacity_label' => '155/100', 'new_count' => 2, 'delivery_mode' => 'ja', 'supplier_location' => 'Power Installation NV',
                'battery_type' => '12V155FS', 'mounting' => 'kast', 'cells_per_battery' => 4, 'first_id_number' => '7001',
            ])
            ->assertSchemaStateSet(['new_capacity_ah' => 155])
            ->call('create')
            ->assertHasNoFormErrors();

        $dossier = EnersysDossier::sole();
        $this->assertSame('enersys', $dossier->kind);
        $this->assertSame(0, BatteryDossier::query()->count(), 'The BAE dossiers do not show the Enersys ones.');
        $this->assertSame('Vervangen van 2 sets batterijen van 0 Ah door 2 sets batterijen van 155/100 Ah', $dossier->description());
        $this->assertSame('Afhalen van 2 sets batterijen van 155/100 Ah te Power Installation NV', $dossier->deliveryLine());
        $this->assertSame(['7001', '7002'], $dossier->batteries->pluck('id_number')->all());

        // The order of the Excel: placing 338 + 4 × 2, removing 334, disposing 6 × 2.
        $this->assertSame(['pickup' => 0.0, 'removal' => 334.0, 'placement' => 346.0, 'disposal' => 12.0, 'other' => 0.0, 'file_costs' => 0.0], $dossier->orderAmounts());
        $dossier->update(['order_amounts' => ['other' => 140, 'other_label' => 'Andere: lekbakken']]);
        $this->assertSame(832.0, $dossier->fresh()->orderTotal());
    }

    #[Test]
    public function the_measurement_follows_the_number_of_batteries_and_blocks(): void
    {
        $this->actingAs($this->technician());
        $dossier = EnersysDossier::factory()->create(['project_id' => $this->project()->id, 'new_count' => 2, 'cells_per_battery' => 4]);

        Livewire::test(EditEnersysDossier::class, ['record' => $dossier->getRouteKey()])
            ->assertSee(__('erp.enersys.disconnected'))
            ->assertDontSee(__('erp.batteries.tabs.single_line'))
            ->assertDontSee(__('erp.batteries.tabs.floor_plan'))
            ->fillForm([
                'average_temperature' => 21,
                'strings' => [
                    1 => ['float_voltage' => 54.4, 'cell_voltages' => [12.8, 12.8, 12.7, 12.8], 'floating_voltages' => [13.6, 13.6, 13.6, 13.6]],
                    2 => ['float_voltage' => 54.3, 'cell_voltages' => [12.7, 12.7, 12.7, 12.7], 'floating_voltages' => [13.5, 13.6, 13.6, 13.6]],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $first = $dossier->fresh()->batteries[0];
        $this->assertSame('54.40', (string) $first->float_voltage);
        $this->assertEqualsWithDelta(51.1, $first->endVoltage(4), 0.001);
        $this->assertEqualsWithDelta(54.4, $first->floatingTotal(4), 0.001);

        // Three batteries: a row more.
        Livewire::test(EditEnersysDossier::class, ['record' => $dossier->getRouteKey()])
            ->fillForm(['new_count' => 3])
            ->call('save');
        $this->assertSame(3, $dossier->fresh()->batteries()->count());
        $this->assertEqualsWithDelta(54.4, $dossier->fresh()->batteries[0]->floatingTotal(4), 0.001, 'Battery 1 keeps its measurements.');
    }

    #[Test]
    public function the_documents_are_made_as_pdf_without_single_line_diagram(): void
    {
        $dossier = EnersysDossier::factory()->create(['project_id' => $this->project()->id]);
        $this->actingAs($this->technician(['view', 'generate_pdf']));

        foreach (['delivery', 'measurement', 'inspection', 'shipping'] as $document) {
            $this->get(route('enersys.document', [$dossier, $document]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }

        $this->get(route('enersys.document', [$dossier, 'single_line']))->assertNotFound();
        $this->get(route('enersys.document', [$dossier, 'floor_plan']))->assertNotFound();
        $this->get(route('enersys.document', [$dossier, 'order']))->assertForbidden();
        $this->get(route('enersys.document', [$dossier, 'all']))->assertOk()->assertDownload();
        $this->get(route('batteries.document', [$dossier->id, 'delivery']))->assertNotFound();

        $this->actingAs($this->technician(['view', 'prices', 'generate_pdf']))->get(route('enersys.document', [$dossier, 'order']))->assertOk();

        $html = view('batteries.enersys.measurement', ['dossier' => $dossier->fresh(['batteries', 'project.workSite']), 'background' => null])->render();
        $this->assertStringContainsString('(In vlotting)', $html);
        $this->assertStringContainsString('Vlotspanning', $html);
        $this->assertStringContainsString('Jacobsveldweg', $html);
    }

    #[Test]
    public function signatures_lock_and_finishing_works_like_the_604(): void
    {
        $technician = $this->technician();
        $this->actingAs($technician);
        $dossier = EnersysDossier::factory()->create(['project_id' => $this->project()->id]);

        Livewire::test(EditEnersysDossier::class, ['record' => $dossier->getRouteKey()])
            ->fillForm(['man_hours' => 3, 'leak_trays' => 2])
            ->callAction(TestAction::make('sign_delivery')->schemaComponent('signature_actions_delivery', schema: 'form'), ['signer_name' => 'Jan Peeters', 'signature' => $this->signature()])
            ->assertHasNoActionErrors();

        $this->assertTrue($dossier->fresh()->isSigned('delivery'));
        $this->assertSame(2, $dossier->fresh()->leak_trays);

        Livewire::test(EditEnersysDossier::class, ['record' => $dossier->getRouteKey()])
            ->assertFormFieldDisabled('leak_trays')
            ->fillForm(['photos' => [UploadedFile::fake()->image('klaar.jpg')]])
            ->callAction('finish');

        $dossier->refresh();
        $this->assertTrue($dossier->isFinished());
        $this->assertSame(ProjectStatus::InvoicingToDo, $dossier->project->status);
        $this->assertFalse($dossier->isEditableBy($technician));
        $this->assertTrue($dossier->isEditableBy($this->technician(['view', 'update', 'edit_finished'])));
    }

    #[Test]
    public function the_enersys_rights_are_separate_from_the_bae_rights(): void
    {
        $dossier = EnersysDossier::factory()->create(['project_id' => $this->project()->id]);

        $this->actingAs(User::factory()->withPermissions(['projects' => ['view'], 'batteries' => ['view', 'update', 'input']])->inTeam($this->team)->create());
        $this->get('/enersys-dossiers')->assertForbidden();
        $this->get('/battery-dossiers')->assertOk()->assertDontSee($dossier->project->reference);

        $this->actingAs($this->technician(['view', 'update']));
        $this->get('/enersys-dossiers')->assertOk()->assertSee($dossier->project->reference);
        $this->get('/battery-dossiers')->assertForbidden();
        Livewire::test(EditEnersysDossier::class, ['record' => $dossier->getRouteKey()])
            ->assertSee(__('erp.batteries.overview.title'))
            ->assertDontSee(__('erp.enersys.tabs.order'));

        $this->setModules(['enersys' => false]);
        $this->get('/enersys-dossiers')->assertForbidden();
    }
}
