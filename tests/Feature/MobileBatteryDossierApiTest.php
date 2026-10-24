<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\BatteryDossier;
use App\Models\EnersysDossier;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileBatteryDossierApiTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->team = Team::factory()->create();
    }

    protected function project(string $category = '604'): Project
    {
        $project = Project::factory()->inCategory($category)->for(WorkSite::factory()->create(['cow_code' => '02LKA', 'postal_code' => '9000', 'city' => 'Gent']))->create();
        $project->teams()->attach($this->team);

        return $project;
    }

    /**
     * A technician: fills in and signs on site, without the Input tab.
     *
     * @return array{Authorization: string}
     */
    protected function technician(string $area = 'batteries'): array
    {
        return $this->mobileAuth(User::factory()->withPermissions(['projects' => ['view'], $area => ['view', 'update']])->inTeam($this->team)->create(['name' => 'Jan', 'locale' => 'nl']));
    }

    /**
     * @return list<list<array{0: int, 1: int}>>
     */
    protected function strokes(): array
    {
        return [[[10, 40], [60, 20], [110, 60], [160, 30]], [[30, 70], [150, 70]]];
    }

    #[Test]
    public function a_technician_fills_in_signs_and_finishes_a_battery_dossier(): void
    {
        $auth = $this->technician();
        $dossier = BatteryDossier::factory()->create(['project_id' => $this->project()->id, 'new_count' => 1, 'cells_per_battery' => 3]);

        $this->getJson('/api/v1/me', $auth)->assertJsonPath('data.features.battery_dossiers', true)->assertJsonPath('data.features.enersys_dossiers', false);
        $this->getJson('/api/v1/dossiers/bae', $auth)->assertOk()->assertJsonPath('data.0.id', $dossier->id)->assertJsonPath('data.0.cow_code', '02LKA');

        $this->getJson("/api/v1/dossiers/bae/{$dossier->id}", $auth)
            ->assertOk()
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.sections.0.key', 'delivery')
            ->assertJsonPath('data.sections.3.key', 'single_line')
            ->assertJsonPath('data.cells', 3)
            ->assertJsonPath('data.signatures.delivery.can_sign', true);

        $this->putJson("/api/v1/dossiers/bae/{$dossier->id}", [
            'values' => ['man_hours' => 6, 'ppe_sets' => 2, 'fuse_type' => 'gG', 'fuse_price' => 999, 'po_number' => 'HACKED', 'measured_on' => '2026-10-07', 'inspection' => ['cells_identical' => 'no'], 'acid_boxes' => 3],
            'batteries' => [1 => ['cell_voltages' => [2.21, 2.22, 2.2], 'cable_type' => 'XVB', 'cables_positive' => 2, 'id_number' => 'HACKED']],
        ], $auth)->assertOk()->assertJsonPath('data.values.man_hours', 6);

        $dossier->refresh();
        $this->assertSame(['6', '2', 'gG', 3], [(string) (float) $dossier->man_hours, (string) (float) $dossier->ppe_sets, $dossier->fuse_type, (int) $dossier->acid_boxes]);
        $this->assertSame('4503359900', $dossier->po_number, 'Input fields need the input permission.');
        $this->assertNotEquals(999, (float) $dossier->fuse_price, 'Prices are not filled in from the app.');
        $this->assertSame([2.21, 2.22, 2.2], $dossier->batteries()->sole()->cell_voltages);
        $this->assertSame('XVB', $dossier->batteries()->sole()->cable_type);
        $this->assertNotSame('HACKED', $dossier->batteries()->sole()->id_number);

        // Signing the delivery note locks its fields.
        $this->postJson("/api/v1/dossiers/bae/{$dossier->id}/signatures/delivery", ['name' => 'Jan Peeters', 'width' => 320, 'height' => 120, 'strokes' => $this->strokes()], $auth)
            ->assertOk()
            ->assertJsonPath('data.signatures.delivery.signed', true)
            ->assertJsonPath('data.signatures.delivery.locked', true);
        $this->assertStringStartsWith("\x89PNG", Storage::disk('local')->get($dossier->signature('delivery')->path));
        $this->postJson("/api/v1/dossiers/bae/{$dossier->id}/signatures/delivery", ['name' => 'Jan', 'width' => 320, 'height' => 120, 'strokes' => $this->strokes()], $auth)->assertStatus(409);

        $this->putJson("/api/v1/dossiers/bae/{$dossier->id}", ['values' => ['man_hours' => 10, 'packages' => 2]], $auth)->assertOk();
        $this->assertSame(6.0, (float) $dossier->fresh()->man_hours);
        $this->assertSame(2, (int) $dossier->fresh()->packages);

        // Finishing needs photos.
        $this->postJson("/api/v1/dossiers/bae/{$dossier->id}/finish", [], $auth)->assertUnprocessable()->assertJsonPath('code', 'photos_required');

        $this->post("/api/v1/dossiers/bae/{$dossier->id}/photos", ['photo' => UploadedFile::fake()->image('batterij.jpg')], [...$auth, 'Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(1, 'data.photos');
        $this->get("/api/v1/dossiers/bae/{$dossier->id}/photos/0", $auth)->assertOk();

        $this->postJson("/api/v1/dossiers/bae/{$dossier->id}/finish", [], $auth)->assertOk()->assertJsonPath('data.finished', true)->assertJsonPath('data.can_edit', false);
        $this->assertSame(ProjectStatus::InvoicingToDo, $dossier->project->fresh()->status);
        $this->putJson("/api/v1/dossiers/bae/{$dossier->id}", ['values' => ['packages' => 5]], $auth)->assertForbidden();
    }

    #[Test]
    public function enersys_dossiers_follow_their_own_rights_and_measure_floating_voltages(): void
    {
        $dossier = EnersysDossier::factory()->create(['project_id' => $this->project('605')->id, 'new_count' => 1, 'cells_per_battery' => 2]);

        $this->getJson('/api/v1/dossiers/enersys', $this->technician())->assertForbidden();

        $auth = $this->technician('enersys');
        $this->getJson("/api/v1/dossiers/enersys/{$dossier->id}", $auth)
            ->assertOk()
            ->assertJsonPath('data.kind', 'enersys')
            ->assertJsonMissingPath('data.sections.5');
        $this->getJson("/api/v1/dossiers/bae/{$dossier->id}", $auth)->assertForbidden();

        $this->putJson("/api/v1/dossiers/enersys/{$dossier->id}", [
            'values' => ['leak_trays' => 2],
            'batteries' => [1 => ['float_voltage' => 27.2, 'cell_voltages' => [12.8, 12.9], 'floating_voltages' => [13.6, 13.6]]],
        ], $auth)->assertOk();

        $battery = $dossier->fresh()->batteries()->sole();
        $this->assertSame(2, (int) $dossier->fresh()->leak_trays);
        $this->assertSame([13.6, 13.6], $battery->floating_voltages);
        $this->assertSame('27.20', $battery->float_voltage);
    }

    #[Test]
    public function other_teams_cannot_open_the_dossier(): void
    {
        $dossier = BatteryDossier::factory()->create(['project_id' => Project::factory()->inCategory('604')->create()->id]);

        $this->getJson("/api/v1/dossiers/bae/{$dossier->id}", $this->technician())->assertForbidden();
    }

    #[Test]
    public function the_floor_plan_is_drawn_in_the_platform_editor_and_saved_through_the_api(): void
    {
        $auth = $this->technician();
        $dossier = BatteryDossier::factory()->create(['project_id' => $this->project()->id]);

        $plan = $this->getJson("/api/v1/dossiers/bae/{$dossier->id}", $auth)->assertOk()->json('data.floor_plan');
        $this->assertFalse($plan['has_drawing']);
        $this->assertNull($plan['image_path']);

        // The signed link opens the editor page, editable for this technician.
        $this->get($plan['editor_url'])->assertOk()->assertSee('data-mobile-floor-plan', false)->assertSee(__('erp.batteries.plan.pen'))->assertSee('data-editable="yes"', false);
        $this->get(route('mobile.floor-plan', ['dossier' => $dossier->id, 'user' => 1]))->assertForbidden();

        // The app saves what the page hands back: the shapes (cleaned) and the PNG for the PDF.
        $png = 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
        $this->putJson("/api/v1/dossiers/bae/{$dossier->id}/floor-plan", [
            'shapes' => [['t' => 'rect', 'x' => 10, 'y' => 20, 'wd' => 300, 'ht' => 120, 'c' => '#dc2626', 'w' => 3, 'label' => 'Batterijkast'], ['t' => 'hack']],
            'png' => $png,
        ], $auth)->assertOk()->assertJsonPath('data.floor_plan.has_drawing', true);

        $dossier->refresh();
        $this->assertCount(1, $dossier->floor_plan);
        $this->assertSame('Batterijkast', $dossier->floor_plan[0]['label']);
        $this->assertNotNull($dossier->floorPlanDataUri());

        $image = $this->getJson("/api/v1/dossiers/bae/{$dossier->id}", $auth)->json('data.floor_plan.image_path');
        $this->get('/api/v1'.$image, $auth)->assertOk()->assertHeader('content-type', 'image/png');

        // A broken picture is refused; an empty drawing removes the picture.
        $this->putJson("/api/v1/dossiers/bae/{$dossier->id}/floor-plan", ['shapes' => [['t' => 'pen', 'pts' => [[1, 1], [5, 5]]]], 'png' => 'data:image/png;base64,bm9wZQ=='], $auth)->assertUnprocessable();
        $this->putJson("/api/v1/dossiers/bae/{$dossier->id}/floor-plan", ['shapes' => [], 'png' => null], $auth)->assertOk()->assertJsonPath('data.floor_plan.has_drawing', false);
        $this->assertNull($dossier->fresh()->floor_plan_png);

        // Read-only for someone who may only look, and not for Enersys dossiers.
        $viewer = User::factory()->withPermissions(['projects' => ['view'], 'batteries' => ['view']])->inTeam($this->team)->create();
        $viewerAuth = $this->mobileAuth($viewer);
        $url = $this->getJson("/api/v1/dossiers/bae/{$dossier->id}", $viewerAuth)->json('data.floor_plan.editor_url');
        $this->get($url)->assertOk()->assertSee('data-editable="no"', false);
        $this->putJson("/api/v1/dossiers/bae/{$dossier->id}/floor-plan", ['shapes' => []], $viewerAuth)->assertForbidden();

        $enersysProject = Project::factory()->inCategory('605')->create();
        $enersysProject->teams()->attach($this->team);
        $enersys = EnersysDossier::factory()->create(['project_id' => $enersysProject->id]);
        $this->getJson("/api/v1/dossiers/enersys/{$enersys->id}", $this->technician('enersys'))->assertOk()->assertJsonPath('data.floor_plan', null);
    }
}
