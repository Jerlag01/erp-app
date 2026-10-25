<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\BatteryRoomDossier;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileBatteryRoomApiTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->team = Team::factory()->create();
    }

    protected function room(): BatteryRoomDossier
    {
        $project = Project::factory()->inCategory('609')->for(WorkSite::factory()->create(['cow_code' => '14EIN', 'postal_code' => '2430', 'city' => 'Eindhout']))->create(['status' => ProjectStatus::WaitingForPo]);
        $project->teams()->attach($this->team);

        return $project->batteryRoom;
    }

    /**
     * @param  list<string>  $abilities
     * @return array{Authorization: string}
     */
    protected function technician(array $abilities = ['view', 'update']): array
    {
        return $this->mobileAuth(User::factory()->withPermissions(['projects' => ['view'], 'battery_rooms' => $abilities])->inTeam($this->team)->create(['locale' => 'nl']));
    }

    #[Test]
    public function a_technician_fills_in_the_delivery_note_and_checklist_of_a_battery_room(): void
    {
        $auth = $this->technician();
        $room = $this->room();

        $this->getJson('/api/v1/me', $auth)->assertJsonPath('data.features.battery_rooms', true)->assertJsonPath('data.features.battery_dossiers', false);
        $this->getJson('/api/v1/battery-rooms', $auth)->assertOk()->assertJsonPath('data.0.id', $room->id)->assertJsonPath('data.0.cow_code', '14EIN')->assertJsonPath('data.0.checklist', '0/14');

        $this->getJson("/api/v1/battery-rooms/{$room->id}", $auth)
            ->assertOk()
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.sections.0.key', 'lighting')
            ->assertJsonPath('data.sections.0.lines.0.label', 'Montagebuis')
            ->assertJsonPath('data.checklist.4.key', 'picto_room')
            ->assertJsonCount(14, 'data.checklist')
            ->assertJsonPath('data.signature.can_sign', true)
            ->assertJsonMissingPath('data.files');

        $this->putJson("/api/v1/battery-rooms/{$room->id}", [
            'room_number' => 'A-1A006',
            'remarks' => 'Sleutel bij de buren.',
            'extra_info' => 'Toegang na 9 uur.',
            'checklist_date' => '2026-11-04',
            'quantities' => ['emergency_light' => ['light' => 1, 'cable' => '8'], 'unknown' => ['x' => 3]],
            'checklist' => ['lighting' => ['ok' => true], 'picto_room' => ['ok' => false, 'action' => 'pictogram aanbrengen']],
        ], $auth)
            ->assertOk()
            ->assertJsonPath('data.values.room_number', 'A-1A006')
            ->assertJsonPath('data.checklist_progress', '1/14');

        $room->refresh();
        $this->assertSame([1.0, 8.0], [$room->quantity('emergency_light', 'light'), $room->quantity('emergency_light', 'cable')]);
        $this->assertArrayNotHasKey('unknown', (array) $room->quantities);
        $this->assertSame('pictogram aanbrengen', $room->action('picto_room'));
        $this->assertSame('Toegang na 9 uur.', $room->extra_info);

        // Invalid numbers are refused.
        $this->putJson("/api/v1/battery-rooms/{$room->id}", ['quantities' => ['lighting' => ['tube' => 'veel']]], $auth)->assertUnprocessable();
    }

    #[Test]
    public function photos_are_added_and_the_delivery_note_is_signed_from_the_phone(): void
    {
        $auth = $this->technician();
        $room = $this->room();

        $this->post("/api/v1/battery-rooms/{$room->id}/photos", ['photo' => UploadedFile::fake()->image('lokaal.jpg')], $auth)->assertCreated()->assertJsonCount(1, 'data.photos');
        $this->get("/api/v1/battery-rooms/{$room->id}/photos/0", $auth)->assertOk();
        $path = $room->refresh()->photos[0];
        $this->deleteJson("/api/v1/battery-rooms/{$room->id}/photos/0", [], $auth)->assertOk()->assertJsonCount(0, 'data.photos');
        Storage::disk('local')->assertMissing($path);

        // The documents stay on the platform.
        $this->post("/api/v1/battery-rooms/{$room->id}/files/btv", ['file' => UploadedFile::fake()->create('keuring.pdf', 30, 'application/pdf')], $auth)->assertNotFound();

        $this->putJson("/api/v1/battery-rooms/{$room->id}", ['remarks' => 'Voor ondertekening.', 'quantities' => ['esd' => ['hours' => 1]]], $auth)->assertOk();
        $this->postJson("/api/v1/battery-rooms/{$room->id}/sign", ['name' => 'Piet Klant', 'width' => 300, 'height' => 120, 'strokes' => [[[10, 40], [60, 20], [110, 60]]]], $auth)
            ->assertOk()
            ->assertJsonPath('data.signature.signed', true)
            ->assertJsonPath('data.signature.locked', true)
            ->assertJsonPath('data.signature.signer_name', 'Piet Klant');
        $this->postJson("/api/v1/battery-rooms/{$room->id}/sign", ['name' => 'X', 'width' => 300, 'height' => 120, 'strokes' => [[[1, 1], [9, 9]]]], $auth)->assertConflict();

        // Signed: the delivery note no longer changes, the checklist and extra info still do.
        $this->putJson("/api/v1/battery-rooms/{$room->id}", ['remarks' => 'Gewijzigd', 'quantities' => ['esd' => ['hours' => 5]], 'extra_info' => 'Nog info', 'checklist' => ['btv' => ['ok' => true]]], $auth)->assertOk();
        $room->refresh();
        $this->assertSame(['Voor ondertekening.', 1.0, 'Nog info', true], [$room->remarks, $room->quantity('esd', 'hours'), $room->extra_info, $room->isOk('btv')]);
        Storage::disk('local')->assertExists($room->signature_path);
    }

    #[Test]
    public function only_seeing_or_a_closed_project_cannot_change_it(): void
    {
        $room = $this->room();
        $viewer = $this->technician(['view']);

        $this->getJson("/api/v1/battery-rooms/{$room->id}", $viewer)->assertOk()->assertJsonPath('data.can_edit', false);
        $this->putJson("/api/v1/battery-rooms/{$room->id}", ['remarks' => 'x'], $viewer)->assertForbidden();

        $this->getJson('/api/v1/battery-rooms', $this->mobileAuth(User::factory()->withPermissions(['projects' => ['view']])->inTeam($this->team)->create()))->assertForbidden();

        $auth = $this->technician();
        $room->project->forceFill(['archived_at' => now(), 'status' => ProjectStatus::Closed])->saveQuietly();
        $this->getJson("/api/v1/battery-rooms/{$room->id}", $auth)->assertJsonPath('data.can_edit', false)->assertJsonPath('data.closed', true);
        $this->putJson("/api/v1/battery-rooms/{$room->id}", ['remarks' => 'x'], $auth)->assertForbidden();
    }
}
