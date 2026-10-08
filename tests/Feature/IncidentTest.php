<?php

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Filament\Admin\Resources\Incidents\Pages\ListIncidents;
use App\Filament\Admin\Resources\Incidents\Pages\ViewIncident;
use App\Filament\App\Pages\ReportIncident;
use App\Models\Incident;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkSite;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_employee_sees_the_highlighted_report_link_on_the_dashboard(): void
    {
        $guest = User::factory()->create(); // no team: guest role, no permissions at all

        $this->actingAs($guest)->get('/')->assertOk()->assertSee('data-report-incident', escape: false)->assertSee(ReportIncident::getUrl(panel: 'app'));
        $this->actingAs($guest)->get('/report-incident')->assertOk();
    }

    #[Test]
    public function the_report_is_linked_to_the_reporter_with_the_system_time(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->inTeam()->create(['name' => 'Eva Peeters']);
        $location = Location::factory()->create(['name' => 'Depot Luik']);

        $this->actingAs($employee);
        Filament::setCurrentPanel('app');

        // The clock is not frozen here: Livewire's temporary uploads carry their own timestamps.
        $before = now()->subSecond();

        Livewire::test(ReportIncident::class)
            ->fillForm([
                'type' => 'accident',
                'place_type' => 'location',
                'place_id' => $location->id,
                'location_details' => 'Laadkade',
                'other_victims' => true,
                'other_victims_details' => 'Collega Jan, snijwonde aan de hand',
                'material_damage' => true,
                'material_damage_details' => 'Rek omgevallen',
                'description' => 'Bij het lossen viel een rek om.',
                'photos' => [
                    UploadedFile::fake()->image('rek.jpg'),
                    UploadedFile::fake()->image('hand.png'),
                    UploadedFile::fake()->createWithContent('iphone.heic', "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 64)),
                ],
            ])
            ->call('submit')
            ->assertHasNoFormErrors()
            ->assertNotified(__('erp.incidents.thanks'));

        $incident = Incident::sole();
        $this->assertTrue($incident->user->is($employee));
        $this->assertSame('Eva Peeters', $incident->reporter_name);
        $this->assertTrue($incident->reported_at->between($before, now()->addSecond()), 'The system time of the report is used.');
        $this->assertSame(IncidentType::Accident, $incident->type);
        $this->assertTrue($incident->place->is($location));
        $this->assertSame('Depot Luik', $incident->place_label);
        $this->assertTrue($incident->other_victims);
        $this->assertTrue($incident->material_damage);
        $this->assertSame(IncidentStatus::New, $incident->status);
        $this->assertCount(3, $incident->photos);
        foreach ($incident->photos as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $this->assertCount(1, $admin->notifications);
    }

    #[Test]
    public function a_work_site_can_be_the_location(): void
    {
        $site = WorkSite::factory()->create(['cow_code' => '02GAM']);
        $this->actingAs(User::factory()->inTeam()->create());
        Filament::setCurrentPanel('app');

        Livewire::test(ReportIncident::class)
            ->fillForm(['type' => 'dangerous_situation', 'place_type' => 'work_site', 'place_id' => $site->id, 'description' => 'Losliggende kabels in de gang.'])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertTrue(Incident::sole()->place->is($site));
        $this->assertStringStartsWith('02GAM', Incident::sole()->place_label);
    }

    #[Test]
    public function svg_files_are_refused_and_a_description_is_required(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->inTeam()->create());
        Filament::setCurrentPanel('app');

        Livewire::test(ReportIncident::class)
            ->fillForm(['type' => 'near_miss', 'location_details' => 'Werf', 'photos' => [UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml')]])
            ->call('submit')
            ->assertHasFormErrors(['description' => 'required', 'photos']);

        $this->assertSame(0, Incident::count());
    }

    #[Test]
    public function photos_are_private_and_only_shown_to_the_reporter_and_administrators(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('incidents/2026/10/a.jpg', UploadedFile::fake()->image('a.jpg')->getContent());
        $reporter = User::factory()->create();
        $incident = Incident::factory()->for($reporter)->create(['photos' => ['incidents/2026/10/a.jpg']]);
        $url = route('incidents.photo', ['incident' => $incident, 'index' => 0]);

        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($reporter)->get($url)->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($reporter)->get(route('incidents.photo', ['incident' => $incident, 'index' => 5]))->assertNotFound();
    }

    #[Test]
    public function the_admin_page_shows_incidents_per_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $eva = User::factory()->create(['name' => 'Eva Peeters']);
        $jan = User::factory()->create(['name' => 'Jan Janssens']);
        $evaIncidents = Incident::factory()->count(2)->for($eva)->create();
        $janIncident = Incident::factory()->for($jan)->create(['type' => IncidentType::Accident]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        $this->get('/admin/incidents')->assertOk()->assertSee('Eva Peeters')->assertSee('Jan Janssens');

        Livewire::test(ListIncidents::class)
            ->assertCanSeeTableRecords([...$evaIncidents, $janIncident])
            ->filterTable('user', $eva->id)
            ->assertCanSeeTableRecords($evaIncidents)
            ->assertCanNotSeeTableRecords([$janIncident]);

        $this->get("/admin/incidents/{$janIncident->id}")->assertOk()->assertSee(__('erp.enums.incident_type.accident'));
    }

    #[Test]
    public function administrators_record_the_follow_up(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewIncident::class, ['record' => $incident->getRouteKey()])
            ->callAction('handle', ['status' => 'closed', 'follow_up' => 'Rek verankerd aan de muur.'])
            ->assertHasNoActionErrors();

        $incident->refresh();
        $this->assertSame(IncidentStatus::Closed, $incident->status);
        $this->assertTrue($incident->handler->is($admin));
    }

    #[Test]
    public function incidents_are_exported_to_pdf_by_administrators_only(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('incidents/a.jpg', UploadedFile::fake()->image('a.jpg', 300, 200)->getContent());
        $incidents = Incident::factory()->count(2)->create(['photos' => ['incidents/a.jpg']]);
        $url = route('incidents.pdf', ['incidents' => $incidents->modelKeys()]);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $response = $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('incidents-', (string) $response->headers->get('Content-Disposition'));

        $single = $this->get(route('incidents.pdf', ['incidents' => [$incidents[0]->id]]));
        $this->assertStringContainsString('incident-'.$incidents[0]->id, (string) $single->headers->get('Content-Disposition'));
    }

    #[Test]
    public function employees_cannot_open_the_admin_incident_page(): void
    {
        $this->actingAs(User::factory()->withPermissions(['vehicles' => ['view']])->inTeam()->create())->get('/admin/incidents')->assertForbidden();
    }

    #[Test]
    public function the_module_can_be_switched_off(): void
    {
        $employee = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->setModules(['incidents' => false]);

        $this->actingAs($employee)->get('/')->assertOk()->assertDontSee('data-report-incident', escape: false);
        $this->actingAs($employee)->get('/report-incident')->assertForbidden();
        $this->actingAs($admin)->get('/admin/incidents')->assertForbidden();
    }
}
