<?php

namespace Tests\Feature;

use App\Actions\Batteries\BatteryDocument;
use App\Enums\BuildingType;
use App\Enums\ProjectStatus;
use App\Filament\Resources\EnersysDossiers\Pages\CreateEnersysDossier;
use App\Filament\Resources\MoveDossiers\MoveDossierResource;
use App\Filament\Resources\MoveDossiers\Pages\CreateMoveDossier;
use App\Filament\Resources\MoveDossiers\Pages\EditMoveDossier;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Models\BatteryModel;
use App\Models\MoveDossier;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteArea;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MoveDossierTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected WorkSite $source;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Filament::setCurrentPanel('app');
        $this->team = Team::factory()->create();
        $this->source = WorkSite::factory()->create(['cow_code' => '02GAM', 'city' => 'Gent', 'building_type' => BuildingType::Atmk]);
    }

    /**
     * @param  list<string>  $enersys
     */
    protected function technician(array $enersys = ['view', 'create', 'update', 'delete', 'input', 'generate_pdf']): User
    {
        return User::factory()->withPermissions(['projects' => ['view'], 'enersys' => $enersys])->inTeam($this->team)->create();
    }

    protected function project(): Project
    {
        $site = WorkSite::query()->where('cow_code', '14EIN')->first() ?? WorkSite::factory()->create(['cow_code' => '14EIN', 'street' => 'Steenweg op Meerhout', 'house_number' => '4', 'postal_code' => '2430', 'city' => 'EINDHOUT', 'work_site_area_id' => WorkSiteArea::query()->firstOrCreate(['name' => '2-VLA'])->id]);
        $project = Project::factory()->inCategory('605')->for($site)->create(['status' => ProjectStatus::InProgress]);
        $project->teams()->attach($this->team);

        return $project;
    }

    #[Test]
    public function a_605_project_is_an_enersys_replacement_or_a_battery_move(): void
    {
        $this->actingAs($this->technician());
        $project = $this->project();

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertActionVisible('enersysDossier')
            ->assertActionVisible('moveDossier');

        Livewire::test(CreateMoveDossier::class)
            ->fillForm([
                'project_id' => $project->id,
                'move_from_work_site_id' => $this->source->id,
                'battery_model_id' => BatteryModel::query()->where('name', '7 OPzV 490')->value('id'),
                'new_technology' => 'OPzV',
                'new_capacity_ah' => 490,
                'new_count' => 2,
                'cells_per_battery' => 24,
                'decommission_source' => true,
                'removal' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $move = MoveDossier::query()->sole();
        $this->assertSame(['02GAM', '14EIN'], [$move->sourceCowCode(), $move->cowCode()]);
        $this->assertStringContainsString('van 02GAM naar 14EIN', $move->description());
        $this->assertCount(2, $move->batteries);

        // Once it is a move, there is no Enersys dossier any more.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertActionHidden('enersysDossier')
            ->assertActionVisible('moveDossier');
        Livewire::test(CreateEnersysDossier::class)
            ->fillForm(['project_id' => $project->id, 'new_count' => 1, 'cells_per_battery' => 4])
            ->call('create')
            ->assertHasFormErrors(['project_id']);
    }

    #[Test]
    public function the_documents_follow_the_604_dossier_with_a_shipping_note_per_transport(): void
    {
        $this->actingAs($this->technician());
        $move = MoveDossier::factory()->create(['project_id' => $this->project()->id, 'move_from_work_site_id' => $this->source->id, 'move_packages' => 2, 'move_weight_kg' => 84]);

        // Without old batteries: no waste shipping note.
        $this->assertSame(['delivery', 'measurement', 'inspection', 'floor_plan', 'single_line', 'shipping_move'], $move->availableDocuments());
        $this->get(route('moves.document', [$move, 'shipping']))->assertNotFound();

        $html = app(BatteryDocument::class)->html($move, 'shipping_move');
        $this->assertStringContainsString('Verzendnota verplaatsing', $html);
        $this->assertStringContainsString('02GAM', $html);
        $this->assertStringContainsString('14EIN', $html);
        $this->assertStringContainsString('84 Kg', $html);
        $this->assertStringContainsString('Wegnemen van 2 batterijen te 02GAM', app(BatteryDocument::class)->html($move, 'delivery'));

        foreach ($move->availableDocuments() as $document) {
            $this->get(route('moves.document', [$move, $document]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }

        // With old batteries at the destination: their own (waste) shipping note.
        $move->update(['removal' => true, 'old_count' => 2, 'old_capacity_ah' => 300]);
        $this->assertContains('shipping', $move->fresh()->availableDocuments());
        $this->get(route('moves.document', [$move, 'shipping']))->assertOk();
        $this->assertStringContainsString('Wegnemen oude batterijen te 14EIN', app(BatteryDocument::class)->html($move->fresh(), 'delivery'));
    }

    #[Test]
    public function finishing_decommissions_the_source_site_with_a_log_line_and_no_609_follows(): void
    {
        $user = $this->technician();
        $this->actingAs($user);
        Storage::disk('local')->put('batteries/1/photos/a.jpg', UploadedFile::fake()->image('a.jpg')->getContent());
        $move = MoveDossier::factory()->create(['project_id' => $this->project()->id, 'move_from_work_site_id' => $this->source->id, 'decommission_source' => true, 'photos' => ['batteries/1/photos/a.jpg']]);

        // The delivery note is signed as on the 604.
        Livewire::test(EditMoveDossier::class, ['record' => $move->getRouteKey()])
            ->assertSuccessful()
            ->callAction('finish');

        $this->source->refresh();
        $this->assertSame(BuildingType::Decommissioned, $this->source->building_type);
        $change = $this->source->typeChanges()->latest('id')->sole();
        $this->assertSame([BuildingType::Atmk, $user->id], [$change->from_type, $change->user_id]);
        $this->assertStringContainsString($move->reference(), (string) $change->reason);
        $this->assertNotNull($move->refresh()->decommissioned_at);
        $this->assertSame(ProjectStatus::InvoicingToDo, $move->project->refresh()->status);

        // Closing the 605 makes no battery room (609), also in Flanders.
        $move->project->update(['status' => ProjectStatus::Closed]);
        $this->assertSame(0, Project::query()->where('source_project_id', $move->project_id)->count());

        // Without the choice the source stays as it is.
        $other = WorkSite::factory()->create(['building_type' => BuildingType::Ldc]);
        $kept = MoveDossier::factory()->create(['project_id' => $this->project()->id, 'move_from_work_site_id' => $other->id, 'decommission_source' => false, 'photos' => ['x.jpg']]);
        $kept->finish($user);
        $this->assertSame(BuildingType::Ldc, $other->refresh()->building_type);
    }

    #[Test]
    public function the_mobile_app_shows_the_move_like_the_604_with_its_shipping_notes_signing_and_finishing(): void
    {
        $user = $this->technician(['view', 'update']);
        $auth = $this->mobileAuth($user);
        $move = MoveDossier::factory()->create(['project_id' => $this->project()->id, 'move_from_work_site_id' => $this->source->id, 'decommission_source' => true, 'new_count' => 1, 'cells_per_battery' => 3]);

        $this->getJson('/api/v1/me', $auth)->assertJsonPath('data.features.move_dossiers', true);
        $this->getJson('/api/v1/dossiers/move', $auth)->assertOk()->assertJsonPath('data.0.id', $move->id);

        $response = $this->getJson("/api/v1/dossiers/move/{$move->id}", $auth)
            ->assertOk()
            ->assertJsonPath('data.kind', 'move')
            ->assertJsonPath('data.floor_plan.title', __('erp.batteries.tabs.floor_plan', [], 'nl'));
        $keys = array_column($response->json('data.sections'), 'key');
        $this->assertSame(['delivery', 'measurement', 'inspection', 'single_line', 'shipping_move'], $keys);
        $this->assertStringContainsString('kind=move', $response->json('data.floor_plan.editor_url'));
        $this->get($response->json('data.floor_plan.editor_url'))->assertOk();

        $this->putJson("/api/v1/dossiers/move/{$move->id}", ['values' => ['vertical_pickup_m' => 3, 'move_packages' => 2, 'move_weight_kg' => 84]], $auth)->assertOk();
        $this->assertSame(['3.00', 2, '84.00'], [$move->refresh()->vertical_pickup_m, $move->move_packages, $move->move_weight_kg]);

        $this->postJson("/api/v1/dossiers/move/{$move->id}/signatures/delivery", ['name' => 'Piet Klant', 'width' => 300, 'height' => 120, 'strokes' => [[[10, 40], [60, 20], [110, 60]]]], $auth)
            ->assertOk()
            ->assertJsonPath('data.signatures.delivery.signed', true);

        $this->post("/api/v1/dossiers/move/{$move->id}/photos", ['photo' => UploadedFile::fake()->image('klaar.jpg')], $auth)->assertCreated();
        $this->postJson("/api/v1/dossiers/move/{$move->id}/finish", [], $auth)->assertOk()->assertJsonPath('data.finished', true);
        $this->assertSame(BuildingType::Decommissioned, $this->source->refresh()->building_type);
    }

    #[Test]
    public function moves_need_the_605_permissions(): void
    {
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view'], 'batteries' => ['view']])->inTeam($this->team)->create());
        $this->get(MoveDossierResource::getUrl('index'))->assertForbidden();

        $this->actingAs($this->technician(['view']));
        $this->get(MoveDossierResource::getUrl('index'))->assertOk();
    }
}
