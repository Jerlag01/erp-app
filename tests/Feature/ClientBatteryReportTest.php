<?php

namespace Tests\Feature;

use App\Enums\GpwStatus;
use App\Enums\ProjectFileSection;
use App\Enums\ProjectStatus;
use App\Filament\Client\Pages\Dashboard;
use App\Filament\Client\Resources\BatteryDeliveries\Pages\ListClientBatteryDeliveries;
use App\Filament\Client\Resources\BatteryReports\ClientBatteryReportResource;
use App\Filament\Client\Resources\BatteryReports\Pages\ListClientBatteryReports;
use App\Filament\Client\Resources\BatteryReports\Pages\ViewClientBatteryReport;
use App\Filament\Client\Resources\Projects\ClientProjectResource;
use App\Filament\Client\Resources\Projects\Pages\ListClientProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\BaeMeasurementReportsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\WasteReportsRelationManager;
use App\Models\BatteryDelivery;
use App\Models\BatteryDossier;
use App\Models\ClientUser;
use App\Models\GpwRequest;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectFile;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteArea;
use App\Models\WorkSiteContact;
use App\Support\ClientPortal;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The client portal's "Battery dossiers" (604 from "to be invoiced"), the waste report zone on the
 * project, the project description under the category and the open / closed share per area.
 */
class ClientBatteryReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function site(string $area = '1-VLA', string $cow = '14EIN'): WorkSite
    {
        return WorkSite::factory()->create(['cow_code' => $cow, 'city' => 'EINDHOUT', 'work_site_area_id' => WorkSiteArea::query()->firstOrCreate(['name' => $area])->id]);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function client(array $permissions = [ClientPortal::PROJECTS, ClientPortal::BATTERY_REPORTS, ClientPortal::ALL_PROJECTS]): ClientUser
    {
        $client = ClientUser::factory()->withPermissions($permissions)->create(['project_categories' => ClientPortal::categories()]);
        $this->actingAs($client, ClientPortal::GUARD);
        Filament::setCurrentPanel(ClientPortal::PANEL);

        return $client;
    }

    #[Test]
    public function a_finished_604_shows_its_photos_documents_and_waste_report_to_the_client(): void
    {
        $done = Project::factory()->inCategory('604')->for($this->site())->create(['status' => ProjectStatus::InvoicingToDo, 'short_description' => 'Batterijen vervangen kelder']);
        $busy = Project::factory()->inCategory('604')->for($this->site('1-VLA', '15EIN'))->create(['status' => ProjectStatus::InProgress]);
        Storage::disk('local')->put('batteries/1/photos/a.jpg', 'jpeg-a');
        BatteryDossier::factory()->create(['project_id' => $done->id, 'photos' => ['batteries/1/photos/a.jpg', 'batteries/1/photos/weg.jpg']]);

        // The waste report is uploaded afterwards, in its own tab on the 604 (not on other projects).
        $employee = User::factory()->admin()->create();
        $this->actingAs($employee);
        Filament::setCurrentPanel('app');
        $this->assertTrue(WasteReportsRelationManager::canViewForRecord($done, ViewProject::class));
        $this->assertFalse(WasteReportsRelationManager::canViewForRecord(Project::factory()->inCategory('610')->create(), ViewProject::class));
        $this->assertTrue(ProjectFileSection::WasteReports->accepts('afval.pdf'));
        $this->assertFalse(ProjectFileSection::WasteReports->accepts('afval.xlsx'));
        $waste = ProjectFile::store($done, ProjectFileSection::WasteReports, UploadedFile::fake()->create('afvalrapport.pdf', 20, 'application/pdf'), 'afvalrapport.pdf', $employee);
        // The measurement report of BAE: its own tab on the 604 too.
        $this->assertTrue(BaeMeasurementReportsRelationManager::canViewForRecord($done, ViewProject::class));
        $this->assertFalse(BaeMeasurementReportsRelationManager::canViewForRecord(Project::factory()->inCategory('610')->create(), ViewProject::class));
        $this->assertFalse(ProjectFileSection::BaeMeasurementReports->accepts('meting.xlsx'));
        $bae = ProjectFile::store($done, ProjectFileSection::BaeMeasurementReports, UploadedFile::fake()->create('meetrapport-bae.pdf', 20, 'application/pdf'), 'meetrapport-bae.pdf', $employee);

        $this->client();
        Livewire::test(ListClientBatteryReports::class)
            ->assertCanSeeTableRecords([$done])
            ->assertCanNotSeeTableRecords([$busy])
            ->assertSee('Batterijen vervangen kelder');
        Livewire::test(ViewClientBatteryReport::class, ['record' => $done->getRouteKey()])
            ->assertSee(__('erp.batteries.documents.measurement'))
            ->assertSee(__('erp.batteries.documents.invoice_request'))
            ->assertSee('afvalrapport.pdf')
            ->assertSee(__('erp.enums.project_file_section.bae_measurements'))
            ->assertSee('meetrapport-bae.pdf');

        foreach (['measurement', 'delivery', 'inspection', 'invoice_request'] as $document) {
            $response = $this->get(route('client.battery-report.document', [$done, $document]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
        $this->get(route('client.battery-report.document', [$done, 'floor_plan']))->assertNotFound();
        $this->assertSame('jpeg-a', $this->get(route('client.battery-report.photo', [$done, 0]))->assertOk()->streamedContent());
        $this->get(route('client.battery-report.photo', [$done, 1]))->assertNotFound();
        $this->get(route('client.battery-report.waste', [$done, $waste]))->assertOk();
        $this->get(route('client.battery-report.waste', [$done, $bae]))->assertOk();
        // Other upload zones of the project stay closed to the client.
        $internal = ProjectFile::store($done, ProjectFileSection::Documents, UploadedFile::fake()->create('intern.pdf', 5), 'intern.pdf', $employee);
        $this->get(route('client.battery-report.waste', [$done, $internal]))->assertNotFound();

        // Not before "to be invoiced", and not without the permission.
        $this->get(route('client.battery-report.document', [$busy, 'measurement']))->assertNotFound();
        $this->client([ClientPortal::PROJECTS, ClientPortal::ALL_PROJECTS]);
        $this->get(ClientBatteryReportResource::getUrl())->assertForbidden();
        $this->get(route('client.battery-report.photo', [$done, 0]))->assertNotFound();
    }

    #[Test]
    public function the_client_sees_the_project_description_under_the_category_and_the_share_per_area(): void
    {
        $vla = $this->site('1-VLA', '01AAA');
        $bru = $this->site('3-BRU', '03BBB');
        $described = Project::factory()->inCategory('604')->for($vla)->create(['status' => ProjectStatus::Closed, 'short_description' => 'Batterijen vervangen kelder']);
        Project::factory()->inCategory('604')->for($vla)->create(['status' => ProjectStatus::InProgress]);
        Project::factory()->inCategory('604')->for($vla)->create(['status' => ProjectStatus::Planned]);
        Project::factory()->inCategory('604')->for($vla)->create(['status' => ProjectStatus::Closed]);
        Project::factory()->inCategory('604')->for($bru)->create(['status' => ProjectStatus::Closed]);

        ProjectCategory::query()->where('code', '604')->update(['name' => 'Batterijvervanging']);
        $this->client();
        Livewire::test(ListClientProjects::class)
            ->assertSee('Batterijen vervangen kelder')
            ->assertCanSeeTableRecords([$described])
            ->assertTableColumnFormattedStateSet('category.code', '604 · Batterijvervanging', $described);

        $areas = collect(Livewire::test(Dashboard::class)->instance()->areas())->keyBy('area');
        $this->assertSame([4, 2, 2, 50.0, 50.0], [$areas['1-VLA']['total'], $areas['1-VLA']['open'], $areas['1-VLA']['closed'], $areas['1-VLA']['open_percent'], $areas['1-VLA']['closed_percent']]);
        $this->assertSame([1, 0.0, 100.0], [$areas['3-BRU']['total'], $areas['3-BRU']['open_percent'], $areas['3-BRU']['closed_percent']]);
        Livewire::test(Dashboard::class)->assertSee(__('erp.client_portal.areas.title'))->assertSee('1-VLA');
    }

    #[Test]
    public function the_dashboard_counts_the_batteries_still_to_be_delivered(): void
    {
        $open = BatteryDelivery::factory()->count(2)->create();
        BatteryDelivery::factory()->create()->markDelivered(User::factory()->create());

        $this->client([ClientPortal::BATTERY_DELIVERIES, ClientPortal::ALL_PROJECTS]);
        $tile = collect(Livewire::test(Dashboard::class)->instance()->tiles())->firstWhere('label', __('erp.client_portal.deliveries_to_come'));
        $this->assertSame([2, true], [$tile['count'], $tile['highlight']]);

        // All delivered: zero, without highlight; without the permission there is no counter.
        $open->each(fn (BatteryDelivery $delivery) => $delivery->markDelivered(User::factory()->create()));
        $tile = collect(Livewire::test(Dashboard::class)->instance()->tiles())->firstWhere('label', __('erp.client_portal.deliveries_to_come'));
        $this->assertSame([0, false], [$tile['count'], $tile['highlight']]);
        $this->client([ClientPortal::PROJECTS, ClientPortal::ALL_PROJECTS]);
        $this->assertNull(collect(Livewire::test(Dashboard::class)->instance()->tiles())->firstWhere('label', __('erp.client_portal.deliveries_to_come')));
    }

    #[Test]
    public function the_project_list_shows_the_delivery_and_planned_execution_and_a_project_shows_its_overview_without_files(): void
    {
        $contact = WorkSiteContact::factory()->create();
        $site = $this->site('1-VLA', '07OWN');
        $site->update(['street' => 'Kerkstraat', 'house_number' => '5', 'postal_code' => '2000', 'city' => 'Antwerpen']);
        $site->contacts()->attach($contact);
        $project = Project::factory()->inCategory('604')->for($site)->create(['status' => ProjectStatus::Planned, 'short_description' => 'Batterijen vervangen kelder']);
        $delivery = BatteryDelivery::factory()->for($project)->create(['planned_delivery_date' => '2026-11-20', 'order_confirmation' => 'OC-OWN']);
        PlanningItem::factory()->forProject($project)->create(['date' => '2026-11-24']);
        PlanningItem::factory()->forProject($project)->create(['date' => '2026-11-26']);
        $leader = User::factory()->create(['name' => 'Jan Leider', 'email' => 'jan.leider@power.example']);
        $project->leaders()->attach($leader);
        GpwRequest::factory()->for($project)->create(['status' => GpwStatus::Approved, 'starts_on' => '2026-11-24', 'ends_on' => '2026-11-26']);
        ProjectFile::store($project, ProjectFileSection::Documents, UploadedFile::fake()->create('intern-plan.pdf', 5), 'intern-plan.pdf');

        $otherDelivery = BatteryDelivery::factory()->for(Project::factory()->inCategory('604')->for($this->site('2-VLA', '08OTH'))->create())->create(['order_confirmation' => 'OC-OTHER']);

        // Only their own projects' deliveries, unless they may see all.
        $client = ClientUser::factory()->withPermissions([ClientPortal::PROJECTS, ClientPortal::BATTERY_DELIVERIES])->create(['project_categories' => ClientPortal::categories(), 'work_site_contact_id' => $contact->id]);
        $this->actingAs($client, ClientPortal::GUARD);
        Filament::setCurrentPanel(ClientPortal::PANEL);
        Livewire::test(ListClientBatteryDeliveries::class)->assertCanSeeTableRecords([$delivery])->assertCanNotSeeTableRecords([$otherDelivery]);

        // In the project list: "To be delivered" with the expected date, and the days in our planning.
        Livewire::test(ListClientProjects::class)
            ->assertTableColumnStateSet('battery_delivery', __('erp.client_portal.to_be_delivered').' · 20/11/2026', $project)
            ->assertTableColumnStateSet('planned_execution', '24/11/2026 – 26/11/2026', $project);
        $delivery->markDelivered(User::factory()->create());
        Livewire::test(ListClientProjects::class)->assertTableColumnStateSet('battery_delivery', __('erp.client_portal.delivered_state'), $project);

        // The overview of the project: COW code, address, status, GPW and the leaders' e-mail, no files.
        $this->get(ClientProjectResource::getUrl('view', ['record' => $project]))
            ->assertOk()
            ->assertSee('07OWN')
            ->assertSee('Kerkstraat 5')
            ->assertSee($project->fresh()->status->getLabel())
            ->assertSee('24/11/2026')
            ->assertSee(GpwStatus::Approved->getLabel())
            ->assertSee('Jan Leider')
            ->assertSee('jan.leider@power.example')
            ->assertDontSee('intern-plan.pdf');

        // Not a project of another client.
        $this->get(ClientProjectResource::getUrl('view', ['record' => $otherDelivery->project]))->assertNotFound();
        $client->update(['permissions' => [ClientPortal::PROJECTS, ClientPortal::BATTERY_DELIVERIES, ClientPortal::ALL_PROJECTS]]);
        Livewire::test(ListClientBatteryDeliveries::class)->assertCanSeeTableRecords([$otherDelivery]);
    }
}
