<?php

namespace Tests\Feature;

use App\Actions\Batteries\BatteryRoomDocument;
use App\Enums\ProjectStatus;
use App\Filament\Resources\BatteryRooms\BatteryRoomResource;
use App\Filament\Resources\BatteryRooms\Pages\CreateBatteryRoom;
use App\Filament\Resources\BatteryRooms\Pages\EditBatteryRoom;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Models\BatteryDossier;
use App\Models\BatteryRoomDossier;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteArea;
use App\Support\Modules;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BatteryRoomTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Filament::setCurrentPanel('app');
        $this->team = Team::factory()->create();
    }

    /**
     * @param  list<string>  $rooms
     */
    protected function user(array $rooms = ['view', 'update', 'generate_pdf', 'download_pdf'], array $projects = ['view', 'update']): User
    {
        return User::factory()->withPermissions(['projects' => $projects, 'battery_rooms' => $rooms])->inTeam($this->team)->create(['name' => 'Jeroen Lagaet']);
    }

    protected function site(string $area, string $cow = '14EIN', string $postalCode = '2430'): WorkSite
    {
        return WorkSite::factory()->create([
            'cow_code' => $cow, 'street' => 'Steenweg op Meerhout', 'house_number' => '4', 'postal_code' => $postalCode, 'city' => 'EINDHOUT',
            'work_site_area_id' => WorkSiteArea::query()->firstOrCreate(['name' => $area])->id,
        ]);
    }

    /**
     * A closed 604 whose 609 was made, with the room of its battery dossier.
     */
    protected function room(string $area = '2-VLA', string $postalCode = '2430'): BatteryRoomDossier
    {
        $battery = Project::factory()->inCategory('604')->for($this->site($area, postalCode: $postalCode))->create(['status' => ProjectStatus::Executed]);
        $battery->teams()->attach($this->team);
        BatteryDossier::factory()->create(['project_id' => $battery->id, 'new_room' => 'A-1A006']);
        $battery->update(['status' => ProjectStatus::Closed]);

        return Project::query()->where('source_project_id', $battery->id)->sole()->batteryRoom;
    }

    #[Test]
    public function the_609_starts_with_its_battery_room_and_gets_its_po_later(): void
    {
        $this->actingAs($user = $this->user());
        $room = $this->room();
        $project = $room->project;

        $this->assertSame(['A-1A006', '14EIN A-1A006'], [$room->room_number, $room->cowAndRoom()]);
        $this->assertNull($room->clientReference());

        // Proximus gives the PO later: it can be added on the project, and the delivery note shows it.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertActionVisible('addPoNumber')
            ->callAction('addPoNumber', ['number' => '4500123456'])
            ->assertHasNoActionErrors();
        $this->assertSame('4500123456', $room->refresh()->clientReference());

        // The project button opens it.
        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->callAction('batteryRoom')
            ->assertRedirect(BatteryRoomResource::getUrl('edit', ['record' => $room]));
    }

    #[Test]
    public function the_delivery_note_and_checklist_are_filled_in_and_printed(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();

        Livewire::test(EditBatteryRoom::class, ['record' => $room->getRouteKey()])
            ->fillForm([
                'quantities.pictograms.hours' => 0.25,
                'quantities.emergency_light.light' => 1,
                'quantities.emergency_light.cable' => 8,
                'remarks' => 'Sleutel bij de buren.',
                'extra_info' => 'Toegang enkel na 9 uur.',
                'checklist_date' => '2026-02-13',
                'checklist.lighting.ok' => true,
                'checklist.picto_room.ok' => false,
                'checklist.picto_room.action' => 'pictogram aanbrengen',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $room->refresh();
        $this->assertSame('0,25', $room->formattedQuantity('pictograms', 'hours'));
        $this->assertSame([1, 14], $room->checklistProgress());
        $this->assertSame('pictogram aanbrengen', $room->action('picto_room'));

        // Flanders: Dutch, on the letterhead in portrait.
        $note = app(BatteryRoomDocument::class)->html($room, 'delivery_note');
        $this->assertStringContainsString('Leveringsnota', $note);
        $this->assertStringContainsString('Noodlicht boven de toegangsdeur', $note);
        $this->assertStringContainsString('Sleutel bij de buren.', $note);
        $this->assertStringContainsString('in aanvraag', $note);
        $this->assertStringContainsString('class="background"', $note);

        // The checklist: landscape, no letterhead, OK/NOK and the action.
        $checklist = app(BatteryRoomDocument::class)->html($room, 'checklist');
        $this->assertStringContainsString('14EIN A-1A006', $checklist);
        $this->assertStringContainsString('pictogram aanbrengen', $checklist);
        $this->assertStringContainsString('NOK', $checklist);
        $this->assertStringNotContainsString('class="background"', $checklist);

        $this->get(route('battery-rooms.document', ['dossier' => $room, 'document' => 'delivery_note']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('battery-rooms.document', ['dossier' => $room, 'document' => 'checklist']))->assertOk();
    }

    #[Test]
    public function brussels_rooms_are_printed_in_the_brussels_language(): void
    {
        $this->actingAs($this->user());
        $room = $this->room('3-BRU', '1000');

        $this->assertSame('fr', $room->documentLocale());
        $this->assertStringContainsString('Note de livraison', app(BatteryRoomDocument::class)->html($room, 'delivery_note'));
        $this->assertStringContainsString('Action à entreprendre', app(BatteryRoomDocument::class)->html($room, 'checklist'));
    }

    #[Test]
    public function photos_the_ventilation_calculation_and_the_btv_report_are_uploaded(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();

        Livewire::test(EditBatteryRoom::class, ['record' => $room->getRouteKey()])
            ->fillForm([
                'photos' => [UploadedFile::fake()->image('lokaal-1.jpg'), UploadedFile::fake()->image('lokaal-2.jpg')],
                'ventilation_file' => UploadedFile::fake()->create('ventilatie.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'btv_file' => UploadedFile::fake()->create('keuring.pdf', 50, 'application/pdf'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $room->refresh();
        $this->assertCount(2, $room->photos);
        $this->assertSame(['ventilatie.xlsx', 'keuring.pdf'], [$room->fileName('ventilation'), $room->fileName('btv')]);
        Storage::disk('local')->assertExists($room->btv_file);

        $this->get(route('battery-rooms.file', ['dossier' => $room, 'kind' => 'btv']))->assertOk();
        $this->get(route('battery-rooms.file', ['dossier' => $room, 'kind' => 'photo', 'index' => 1]))->assertOk();

        // The BTV PDF needs "download PDF".
        $this->actingAs($this->user(['view', 'update']));
        $this->get(route('battery-rooms.file', ['dossier' => $room, 'kind' => 'btv']))->assertForbidden();
        $this->get(route('battery-rooms.file', ['dossier' => $room, 'kind' => 'ventilation']))->assertOk();
    }

    #[Test]
    public function battery_rooms_have_their_own_permissions_and_module(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();

        $this->actingAs($this->user([]));
        $this->get(BatteryRoomResource::getUrl('index'))->assertForbidden();
        $this->get(route('battery-rooms.document', ['dossier' => $room, 'document' => 'checklist']))->assertForbidden();

        $this->actingAs($this->user(['view', 'update']));
        $this->get(BatteryRoomResource::getUrl('index'))->assertOk()->assertSee($room->project->reference);
        $this->get(route('battery-rooms.document', ['dossier' => $room, 'document' => 'checklist']))->assertForbidden();

        settings()->save('modules', [...settings('modules'), Modules::BATTERY_ROOMS => false]);
        $this->get(BatteryRoomResource::getUrl('index'))->assertForbidden();
    }

    #[Test]
    public function the_delivery_note_is_signed_and_then_locked_except_for_administrators(): void
    {
        $this->actingAs($this->user());
        $room = $this->room();
        $png = 'data:image/png;base64,'.base64_encode((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        Livewire::test(EditBatteryRoom::class, ['record' => $room->getRouteKey()])
            ->fillForm(['remarks' => 'Getekend door de klant.'])
            ->callAction(TestAction::make('sign')->schemaComponent('signature_actions'), ['signer_name' => 'Piet Klant', 'signature' => $png])
            ->assertHasNoActionErrors();

        $room->refresh();
        $this->assertTrue($room->isSigned());
        $this->assertSame(['Piet Klant', 'Getekend door de klant.'], [$room->signer_name, $room->remarks]);
        $this->assertStringContainsString('Piet Klant', app(BatteryRoomDocument::class)->html($room, 'delivery_note'));

        // Locked: saving leaves the delivery note as it was signed.
        Livewire::test(EditBatteryRoom::class, ['record' => $room->getRouteKey()])
            ->assertFormFieldDisabled('remarks')
            ->assertDontSee(__('erp.batteries.signature.remove'))
            ->fillForm(['extra_info' => 'Na de handtekening.'])
            ->call('save');
        $this->assertSame(['Getekend door de klant.', 'Na de handtekening.'], [$room->refresh()->remarks, $room->extra_info]);

        // Administrators can take the signature off again.
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(EditBatteryRoom::class, ['record' => $room->getRouteKey()])
            ->assertFormFieldEnabled('remarks')
            ->callAction(TestAction::make('unsign')->schemaComponent('signature_actions'));
        $this->assertFalse($room->refresh()->isSigned());
    }

    #[Test]
    public function a_battery_room_is_made_by_hand_with_its_609_project(): void
    {
        $user = $this->user(['view', 'create', 'update'], ['view', 'create']);
        $this->actingAs($user);
        $site = $this->site('1-VLA', '02GAM', '9000');

        Livewire::test(CreateBatteryRoom::class)
            ->fillForm(['work_site_id' => $site->id, 'room_number' => 'B-0.12'])
            ->call('create')
            ->assertHasNoFormErrors();

        $room = BatteryRoomDossier::query()->latest('id')->firstOrFail();
        $project = $room->project;
        $this->assertSame(['609', '02GAM', ProjectStatus::WaitingForPo, 'B-0.12'], [$project->category->code, $project->cow_code, $project->status, $room->room_number]);
        $this->assertTrue($project->isLedBy($user));
        $this->assertTrue($project->isVisibleTo($user));

        // Without permission to make projects there is no "New".
        $this->actingAs($this->user(['view', 'create', 'update'], ['view']));
        $this->get(BatteryRoomResource::getUrl('create'))->assertForbidden();
    }
}
