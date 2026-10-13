<?php

namespace Tests\Feature;

use App\Actions\Batteries\BatteryDocument;
use App\Enums\BatteryDeliveryMode;
use App\Enums\BatteryTechnology;
use App\Filament\Resources\BatteryDossiers\BatteryDossierResource;
use App\Filament\Resources\BatteryDossiers\Pages\CreateBatteryDossier;
use App\Filament\Resources\BatteryDossiers\Pages\EditBatteryDossier;
use App\Models\BatteryCable;
use App\Models\BatteryDossier;
use App\Models\BatteryModel;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\BatteryTemplates;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BatteryDossierTest extends TestCase
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

    protected function technician(array $battery = ['view', 'create', 'update', 'delete', 'generate_pdf']): User
    {
        return User::factory()->withPermissions(['projects' => ['view'], 'batteries' => $battery])->inTeam($this->team)->create();
    }

    protected function project(): Project
    {
        $project = Project::factory()->inCategory('604')->for(WorkSite::factory()->create(['cow_code' => '02LKA', 'street' => 'Stationsstraat', 'house_number' => '12', 'postal_code' => '9000', 'city' => 'Gent']))->create();
        $project->teams()->attach($this->team);

        return $project;
    }

    #[Test]
    public function the_battery_list_and_cables_of_the_excel_are_there(): void
    {
        $this->assertSame(21, BatteryModel::count());
        $this->assertSame(11, BatteryCable::count());

        $model = BatteryModel::query()->where('name', '7 OPzV 490')->with('racks')->sole();
        $this->assertSame('24 cellen 7 OPzV 490. Gewicht:42Kg. Afmetingen:l:168 b:208 h:535', $model->description(24));
        $this->assertSame('PGL1-44H', $model->racks->firstWhere('mounting.value', 'lijn')->code);
        $this->assertSame('65.30', $model->racks->firstWhere('mounting.value', 'kast')->price);
        $this->assertSame('85.82', BatteryCable::query()->where('name', '70mm² x 3m')->value('price'));
    }

    #[Test]
    public function the_example_of_the_excel_gives_the_same_texts_and_amounts(): void
    {
        $dossier = BatteryDossier::factory()->create(['project_id' => $this->project()->id, 'first_id_number' => '19311812']);
        $dossier->refresh();

        $this->assertSame('19311812 - 19311813', $dossier->idNumbers());
        $this->assertSame('Vervangen van 2 sets batterijen van 800 Ah door 2 sets batterijen van 500 Ah', $dossier->description());
        $this->assertSame('Plaatsen van 2 nieuwe batterij(en), 7 OPzV 490 Ah van BAE', $dossier->deliveryLine());
        $this->assertSame('Stelling voor OPzV batterij BAE 500Ah -  Kast', $dossier->rackLine());
        $this->assertSame('Vernietigen van (2 batterijen 800 Ah OPzV)', $dossier->destroyLine());
        $this->assertSame(['price' => 799.2, 'formula' => 'Formule: 300 + (1,3 x 8 x 2 x 24)'], $dossier->removalPrice());
        $this->assertEqualsWithDelta(390.6381, $dossier->pricePerCell(), 0.0001);
        // Excel "Fact Aanv NL": 18 750,63 + 48 × 65,30 + 799,20.
        $this->assertSame(22684.23, $dossier->invoiceTotal());

        $dossier->update(['delivery_mode' => BatteryDeliveryMode::OnSite, 'removal' => false, 'invoice_percentage' => 50]);
        $this->assertSame('2 sets batterijen van 500 ter plaatse aanwezig : Power Installation NV', $dossier->deliveryLine());
        $this->assertSame(21885.03, $dossier->invoiceTotal());
        $this->assertSame(10942.52, $dossier->invoiceAmount());
        $this->assertSame('2 sets batterijen van 500 Ah worden bij u geleverd door BAE', $dossier->fill(['delivery_mode' => BatteryDeliveryMode::Delivery])->deliveryLine());
    }

    #[Test]
    public function a_technician_creates_a_dossier_on_a_604_project_of_their_team(): void
    {
        $user = $this->technician();
        $project = $this->project();
        $otherTeams = Project::factory()->inCategory('604')->create();
        $notBattery = Project::factory()->inCategory('605')->create();
        $notBattery->teams()->attach($this->team);
        $this->actingAs($user);

        $component = Livewire::test(CreateBatteryDossier::class);
        $this->assertSame(['BAE', 'Power Installation NV'], [$component->get('data.brand'), $component->get('data.supplier_location')]);

        foreach ([$otherTeams, $notBattery] as $wrong) {
            Livewire::test(CreateBatteryDossier::class)
                ->fillForm(['project_id' => $wrong->id, 'new_count' => 2, 'cells_per_battery' => 24, 'delivery_mode' => 'ja'])
                ->call('create')
                ->assertHasFormErrors(['project_id']);
        }

        Livewire::test(CreateBatteryDossier::class)
            ->fillForm([
                'project_id' => $project->id,
                'old_capacity_ah' => 800, 'old_technology' => 'OPzV', 'old_count' => 2,
                'new_capacity_ah' => 500, 'new_technology' => 'OPzV', 'new_count' => 2,
                'battery_model_id' => BatteryModel::query()->where('name', '7 OPzV 490')->value('id'),
                'mounting' => 'kast', 'cells_per_battery' => 24, 'first_id_number' => '19311812', 'delivery_mode' => 'ja',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $dossier = BatteryDossier::sole();
        $this->assertTrue($dossier->creator->is($user));
        $this->assertSame([1, 2], $dossier->batteries->pluck('position')->all());
        $this->assertSame(['19311812', '19311813'], $dossier->batteries->pluck('id_number')->all());
        $this->assertSame(['gL-gL', 'gL-gL'], $dossier->batteries->pluck('fuse_type')->all());
        $this->assertSame('64.68', (string) $dossier->hourly_rate, 'Prices come from the settings.');
    }

    #[Test]
    public function the_rows_follow_the_number_of_batteries_and_keep_the_measurements(): void
    {
        $this->actingAs($this->technician());
        $dossier = BatteryDossier::factory()->create(['project_id' => $this->project()->id]);

        $voltages = array_fill(0, 24, 2.23);
        Livewire::test(EditBatteryDossier::class, ['record' => $dossier->getRouteKey()])
            ->fillForm([
                'new_count' => 3,
                'average_temperature' => 19.5,
                'strings' => [
                    1 => ['cell_voltages' => $voltages, 'cable_type' => 'XVB', 'cable_length_m' => 6.5, 'cables_positive' => 2, 'cables_negative' => 2, 'cable_section' => '70', 'fuse_amperage' => '250', 'fuse_type' => 'gL-gL'],
                    2 => ['cell_voltages' => [2.2, 2.25]],
                ],
                'inspection' => ['cells_identical' => 'yes', 'damage' => 'no'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $dossier->refresh();
        $this->assertSame(3, $dossier->batteries->count());
        $first = $dossier->batteries[0];
        $this->assertEqualsWithDelta(53.52, $first->endVoltage(24), 0.001);
        $this->assertSame(['XVB', '6.50', 2, 2, '70', '250'], [$first->cable_type, $first->cable_length_m, $first->cables_positive, $first->cables_negative, $first->cable_section, $first->fuse_amperage]);
        $this->assertEqualsWithDelta(4.45, $dossier->batteries[1]->endVoltage(24), 0.001);
        $this->assertNull($dossier->batteries[2]->endVoltage(24));
        $this->assertSame('yes', $dossier->inspectionAnswer('cells_identical'));
        $this->assertSame('no', $dossier->inspectionAnswer('damage'));

        Livewire::test(EditBatteryDossier::class, ['record' => $dossier->getRouteKey()])
            ->fillForm(['new_count' => 1])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([1], $dossier->fresh()->batteries->pluck('position')->all());
        $this->assertEqualsWithDelta(53.52, $dossier->fresh()->batteries[0]->endVoltage(24), 0.001, 'Battery 1 keeps its measurements.');
    }

    #[Test]
    public function the_floor_plan_is_saved_as_drawing_and_image(): void
    {
        $this->actingAs($this->technician());
        $dossier = BatteryDossier::factory()->create(['project_id' => $this->project()->id]);
        $png = 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        Livewire::test(EditBatteryDossier::class, ['record' => $dossier->getRouteKey()])
            ->fillForm([
                'floor_plan' => [
                    ['t' => 'dim', 'x1' => 10, 'y1' => 20, 'x2' => 300, 'y2' => 20, 'c' => '#dc2626', 'w' => 3, 'label' => '2,40 m'],
                    ['t' => 'rect', 'x' => 50, 'y' => 60, 'wd' => 100, 'ht' => 40, 'c' => 'red; evil', 'w' => 99, 'label' => 'BATT 1'],
                    ['t' => 'script', 'x' => 1],
                ],
                'floor_plan_png' => $png,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $dossier->refresh();
        $this->assertCount(2, $dossier->floor_plan);
        $this->assertSame('2,40 m', $dossier->floor_plan[0]['label']);
        $this->assertSame(['#0f172a', 12], [$dossier->floor_plan[1]['c'], $dossier->floor_plan[1]['w']], 'Unknown colours and widths are corrected.');
        Storage::disk('local')->assertExists($dossier->floor_plan_png);
        $this->assertStringStartsWith('data:image/png;base64,', $dossier->floorPlanDataUri());

        Livewire::test(EditBatteryDossier::class, ['record' => $dossier->getRouteKey()])
            ->fillForm(['floor_plan_png' => 'data:image/png;base64,'.base64_encode('<svg>not a png</svg>')])
            ->call('save')
            ->assertHasErrors(['data.floor_plan']);
    }

    #[Test]
    public function the_documents_download_as_pdf_and_prices_need_the_right(): void
    {
        $dossier = BatteryDossier::factory()->create(['project_id' => $this->project()->id, 'new_technology' => BatteryTechnology::OPzS]);
        $this->actingAs($this->technician(['view']));
        $this->get(route('batteries.document', [$dossier, 'delivery']))->assertForbidden();

        $this->actingAs($this->technician(['view', 'generate_pdf']));

        foreach (['delivery', 'measurement', 'inspection', 'floor_plan', 'single_line', 'shipping'] as $document) {
            $this->get(route('batteries.document', [$dossier, $document]))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }

        $this->get(route('batteries.document', [$dossier, 'invoice_request']))->assertForbidden();
        $this->get(route('batteries.document', [$dossier, 'nonsense']))->assertNotFound();
        $this->get(route('batteries.document', [$dossier, 'all']))->assertOk()->assertDownload();

        $this->actingAs($this->technician(['view', 'prices', 'generate_pdf']))
            ->get(route('batteries.document', [$dossier, 'invoice_request']))
            ->assertOk();

        // Someone outside the project's teams.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view'], 'batteries' => ['view', 'generate_pdf']])->inTeam()->create())
            ->get(route('batteries.document', [$dossier, 'delivery']))
            ->assertForbidden();
    }

    #[Test]
    public function an_uploaded_background_replaces_the_standard_letterhead(): void
    {
        $standard = BatteryTemplates::backgroundDataUri();
        $this->assertStringStartsWith('data:image/jpeg;base64,', $standard);

        Storage::disk('public')->put('battery-templates/new.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        settings()->save('batteries', [...settings('batteries'), 'background' => 'battery-templates/new.png']);

        $this->assertStringStartsWith('data:image/png;base64,', BatteryTemplates::backgroundDataUri());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/settings?tab=batterijen::data::tab')->assertOk();
    }

    #[Test]
    public function the_module_can_be_switched_off(): void
    {
        $dossier = BatteryDossier::factory()->create(['project_id' => $this->project()->id]);
        $this->setModules(['batteries' => false]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/battery-dossiers')->assertForbidden();
        $this->actingAs($admin)->get('/battery-models')->assertForbidden();
        $this->actingAs($admin)->get(route('batteries.document', [$dossier, 'delivery']))->assertForbidden();
    }

    #[Test]
    public function the_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        $dossier = BatteryDossier::factory()->create(['project_id' => $this->project()->id]);
        $this->actingAs($admin);

        $this->get('/battery-dossiers')->assertOk()->assertSee($dossier->project->reference);
        $this->get('/battery-dossiers/create')->assertOk();
        $this->get("/battery-dossiers/{$dossier->id}/edit")->assertOk()->assertSee('Krachtbord')->assertSee('data-floor-plan-editor', false);
        $this->get('/battery-models')->assertOk()->assertSee('10 OPzV 1000');
        $this->get('/battery-cables')->assertOk()->assertSee('70mm² x 3m');
        $this->get("/projects/{$dossier->project_id}")->assertOk()->assertSee(BatteryDossierResource::getUrl('edit', ['record' => $dossier]), false);
        $this->get('/battery-dossiers/create?project='.$dossier->project_id)->assertOk();
    }

    #[Test]
    public function the_shipping_note_sender_is_the_address_of_the_cow_site(): void
    {
        $dossier = BatteryDossier::factory()->create(['project_id' => $this->project()->id]);

        $this->assertSame(['Stationsstraat 12', '9000 Gent'], $dossier->fresh()->senderAddressLines());

        $siteless = BatteryDossier::factory()->create(['project_id' => Project::factory()->inCategory('604')->create(['work_site_id' => null])->id]);
        $this->assertSame(['Romeinse Steenweg Paleis 5', '1020 Brussel'], $siteless->fresh()->senderAddressLines(), 'Without a site address the settings are used.');
    }

    protected function signature(): string
    {
        return 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    #[Test]
    public function a_signed_document_is_locked_except_for_administrators(): void
    {
        $technician = $this->technician();
        $this->actingAs($technician);
        $dossier = BatteryDossier::factory()->create(['project_id' => $this->project()->id, 'new_count' => 1, 'cells_per_battery' => 2]);

        // Signing saves what is on screen first.
        Livewire::test(EditBatteryDossier::class, ['record' => $dossier->getRouteKey()])
            ->fillForm(['man_hours' => 6, 'inspection_remarks' => 'Alles in orde', 'average_temperature' => 20, 'strings' => [1 => ['cell_voltages' => [2.2, 2.21]]]])
            ->callAction(TestAction::make('sign_delivery')->schemaComponent('signature_actions_delivery', schema: 'form'), ['signer_name' => 'Jan Peeters', 'signature' => $this->signature()])
            ->assertHasNoActionErrors();

        $dossier->refresh();
        $signature = $dossier->signature('delivery');
        $this->assertSame(['Jan Peeters', $technician->id], [$signature->signer_name, $signature->signed_by]);
        Storage::disk('local')->assertExists($signature->path);
        $this->assertSame('6.00', (string) $dossier->man_hours);

        $dossier->sign('measurement', 'Jan Peeters', $this->signature(), $technician);
        $this->assertTrue($dossier->isLockedFor('delivery', $technician));
        $this->assertFalse($dossier->isLockedFor('inspection', $technician));

        // The technician can no longer change the signed documents, only the others.
        Livewire::test(EditBatteryDossier::class, ['record' => $dossier->getRouteKey()])
            ->assertFormFieldDisabled('man_hours')
            ->assertFormFieldDisabled('average_temperature')
            ->assertFormFieldEnabled('inspection_remarks')
            ->set('data.man_hours', 99)
            ->set('data.average_temperature', 35)
            ->set('data.strings.1.cell_voltages.0', 1.5)
            ->fillForm(['inspection_remarks' => 'Kleine opmerking'])
            ->call('save')
            ->assertHasNoFormErrors();

        $dossier->refresh();
        $this->assertSame(['6.00', 'Kleine opmerking'], [(string) $dossier->man_hours, $dossier->inspection_remarks]);
        $this->assertEqualsWithDelta(20.0, (float) $dossier->average_temperature, 0.001);
        $this->assertEqualsWithDelta(2.2, $dossier->batteries[0]->cell_voltages[0], 0.001);

        // The signature is on the PDF.
        $this->get(route('batteries.document', [$dossier, 'delivery']))->assertOk();

        // An administrator can still change it and remove the signature.
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(EditBatteryDossier::class, ['record' => $dossier->getRouteKey()])
            ->assertFormFieldEnabled('man_hours')
            ->fillForm(['man_hours' => 7])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make('unsign_delivery')->schemaComponent('signature_actions_delivery', schema: 'form'));

        $dossier->refresh();
        $this->assertSame('7.00', (string) $dossier->man_hours);
        $this->assertFalse($dossier->isSigned('delivery'));
        Storage::disk('local')->assertMissing($signature->path);
        $this->assertFalse($dossier->isLockedFor('delivery', $technician));

        // Only administrators remove a signature.
        $this->actingAs($technician);
        Livewire::test(EditBatteryDossier::class, ['record' => $dossier->getRouteKey()])
            ->assertSee(__('erp.batteries.signature.locked'))
            ->assertDontSee(__('erp.batteries.signature.remove'));
    }

    #[Test]
    public function the_shipping_note_fits_on_one_page(): void
    {
        $dossier = BatteryDossier::factory()->create(['project_id' => $this->project()->id, 'acid_boxes' => 3, 'packages' => 2, 'weight_kg' => 950, 'po_number' => '4500012345', 'waste_type' => 'Lood']);

        $pdf = app(BatteryDocument::class)->render($dossier, 'shipping');

        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }
}
