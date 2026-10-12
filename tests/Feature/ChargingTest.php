<?php

namespace Tests\Feature;

use App\Enums\ChargingConnectivity;
use App\Enums\ChargingStationType;
use App\Filament\Admin\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\ChargingStations\Pages\CreateChargingStation;
use App\Filament\Resources\ChargingStations\Pages\EditChargingStation;
use App\Filament\Resources\ChargingStations\Pages\ListChargingStations;
use App\Filament\Resources\Routers\Pages\CreateRouter;
use App\Filament\Resources\Routers\Pages\ListRouters;
use App\Models\ChargingStation;
use App\Models\Project;
use App\Models\Role;
use App\Models\Router;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\ChargingExport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChargingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Filament::setCurrentPanel('app');
    }

    protected function user(bool $secrets): User
    {
        $abilities = ['view', 'create', 'update', 'delete', ...($secrets ? ['secrets'] : [])];

        return User::factory()->withPermissions(['charging' => $abilities, 'routers' => $abilities])->inTeam()->create();
    }

    protected function site(): WorkSite
    {
        return WorkSite::factory()->create(['cow_code' => '02GAM', 'street' => 'Carlistraat', 'house_number' => '2', 'postal_code' => '1140', 'city' => 'Evere']);
    }

    #[Test]
    public function a_station_takes_its_address_from_the_cow_site_and_keeps_its_secrets_encrypted(): void
    {
        $site = $this->site();
        $this->actingAs($this->user(secrets: true));

        Livewire::test(CreateChargingStation::class)
            ->fillForm([
                'work_site_id' => $site->id, 'ace_number' => 'ACE0682661', 'type' => 'cluster', 'cluster_number' => 2, 'system' => 'duo',
                'connectivity' => 'modem_wired', 'brand' => 'ALFEN', 'backoffice' => 'Nexxtmove', 'installation_year' => 2024,
                'password' => 'vZyHCPnoh9HY', 'recovery_code' => '8v#fZn6s6mQkTwFt', 'online' => true, 'in_service' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $station = ChargingStation::sole();
        $this->assertSame('Carlistraat 2, 1140 Evere', $station->address());
        $this->assertSame(ChargingStationType::Cluster, $station->type);
        $this->assertSame(ChargingConnectivity::ModemWired, $station->connectivity);
        $this->assertSame('vZyHCPnoh9HY', $station->password);
        $this->assertNotSame('vZyHCPnoh9HY', DB::table('charging_stations')->value('password'), 'Stored encrypted.');
        $this->assertArrayNotHasKey('password', $station->toArray());

        $this->get("/charging-stations/{$station->id}")->assertOk()->assertSee('vZyHCPnoh9HY')->assertSee('Carlistraat 2, 1140 Evere');
    }

    #[Test]
    public function without_the_secrets_right_passwords_stay_hidden_and_untouched(): void
    {
        $station = ChargingStation::factory()->for($this->site())->create(['password' => 'TopSecret123', 'recovery_code' => 'Recover#456']);
        $this->actingAs($this->user(secrets: false));

        $this->get("/charging-stations/{$station->id}")->assertOk()->assertDontSee('TopSecret123')->assertDontSee('Recover#456');
        $this->get("/charging-stations/{$station->id}/edit")->assertOk()->assertDontSee('TopSecret123');

        Livewire::test(EditChargingStation::class, ['record' => $station->getRouteKey()])
            ->fillForm(['brand' => 'Wallbox'])
            ->call('save')
            ->assertHasNoFormErrors();

        $station->refresh();
        $this->assertSame('Wallbox', $station->brand);
        $this->assertSame('TopSecret123', $station->password, 'Saving without the right keeps the password.');
        $this->assertSame('Recover#456', $station->recovery_code);

        // Online/offline is set by hand in the list.
        Livewire::test(ListChargingStations::class)->call('updateTableColumnState', 'online', (string) $station->getKey(), false);
        $this->assertFalse($station->fresh()->online);
    }

    #[Test]
    public function the_vpn_file_of_a_router_is_only_for_the_secrets_right(): void
    {
        $site = $this->site();
        $this->actingAs($this->user(secrets: true));

        Livewire::test(CreateRouter::class)
            ->fillForm([
                'work_site_id' => $site->id, 'type' => 'Teltonika RUT241', 'serial_number' => '1234567890', 'sim_ean' => '5413729239098',
                'sim_ssn' => '2100450325347', 'username' => 'admin', 'password' => 'R0uterPass!',
                'vpn_file' => UploadedFile::fake()->createWithContent('02GAM.ovpn', "client\nremote vpn.example.be 1194\n"),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $router = Router::sole();
        $this->assertSame('02GAM.ovpn', $router->vpn_file_name);
        Storage::disk('local')->assertExists($router->vpn_file);
        $this->assertSame('R0uterPass!', $router->password);

        $this->get(route('routers.vpn', $router))->assertOk()->assertDownload('02GAM.ovpn');
        $this->get("/routers/{$router->id}")->assertOk()->assertSee('R0uterPass!');

        $this->actingAs($this->user(secrets: false));
        $this->get(route('routers.vpn', $router))->assertForbidden();
        $this->get("/routers/{$router->id}")->assertOk()->assertDontSee('R0uterPass!')->assertDontSee('02GAM.ovpn');
    }

    #[Test]
    public function both_lists_export_to_excel_and_pdf(): void
    {
        $site = $this->site();
        ChargingStation::factory()->for($site)->create(['ace_number' => 'ACE0000001', 'password' => 'Secret-A']);
        ChargingStation::factory()->for(WorkSite::factory()->create(['cow_code' => '03BAC']))->create(['ace_number' => 'ACE0000002', 'online' => false]);
        Router::factory()->for($site)->create(['serial_number' => 'SN-1', 'password' => 'Secret-R']);

        $read = function (string $path): array {
            $reader = new Reader;
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
            $reader->close();

            return $rows;
        };

        $plain = $read(app(ChargingExport::class)->xlsx('Laadpalen', ChargingExport::stationColumns(false), ChargingStation::query()->with('workSite')->orderBy('ace_number')->get()));
        $this->assertSame(['Volledig uitgevoerd', 'CowCode', 'ACE-number', 'EAN SIM'], array_slice($plain[0], 0, 4));
        $this->assertSame(['02GAM', 'ACE0000001'], [$plain[1][1], $plain[1][2]]);
        $this->assertContains('Carlistraat 2, 1140 Evere', $plain[1]);
        $this->assertSame('OFFLINE', $plain[2][array_search('Online/Offline', $plain[0], true)]);
        $this->assertNotContains('Secret-A', $plain[1]);

        $secret = $read(app(ChargingExport::class)->xlsx('Laadpalen', ChargingExport::stationColumns(true), ChargingStation::query()->with('workSite')->orderBy('ace_number')->get()));
        $this->assertSame('Secret-A', $secret[1][array_search('Password', $secret[0], true)]);

        $this->actingAs($this->user(secrets: false));
        Livewire::test(ListChargingStations::class)->callAction('export_xlsx')->assertFileDownloaded();
        Livewire::test(ListChargingStations::class)->callAction('export_pdf')->assertFileDownloaded();
        Livewire::test(ListRouters::class)->callAction('export_xlsx')->assertFileDownloaded();
        Livewire::test(ListRouters::class)->callAction('export_pdf')->assertFileDownloaded();
    }

    #[Test]
    public function a_608_project_lists_its_charging_stations(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->inCategory('608')->for($this->site())->create();
        $station = ChargingStation::factory()->create(['work_site_id' => $project->work_site_id, 'project_id' => $project->id, 'ace_number' => 'ACE0608591']);
        $this->actingAs($admin);

        $this->get("/projects/{$project->id}")->assertOk()->assertSee(__('erp.charging.stations'));
        $this->get('/charging-stations/create?project='.$project->id)->assertOk();
        $this->get('/charging-stations')->assertOk()->assertSee('ACE0608591');
        $this->get('/routers')->assertOk();

        $this->setModules(['charging' => false]);
        $this->get('/charging-stations')->assertForbidden();
        $this->get('/routers')->assertForbidden();
        $this->assertNotNull($station->fresh());
    }

    #[Test]
    public function stations_and_routers_have_their_own_permissions(): void
    {
        $site = $this->site();
        $station = ChargingStation::factory()->for($site)->create(['password' => 'Station-Secret']);
        $router = Router::factory()->for($site)->create(['password' => 'Router-Secret']);

        $stationsOnly = User::factory()->withPermissions(['charging' => ['view', 'secrets']])->inTeam()->create();
        $this->actingAs($stationsOnly);
        $this->get("/charging-stations/{$station->id}")->assertOk()->assertSee('Station-Secret');
        $this->get('/routers')->assertForbidden();

        $routersOnly = User::factory()->withPermissions(['routers' => ['view']])->inTeam()->create();
        $this->actingAs($routersOnly);
        $this->get('/charging-stations')->assertForbidden();
        $this->get("/routers/{$router->id}")->assertOk()->assertDontSee('Router-Secret');
    }

    #[Test]
    public function administrators_only_see_router_credentials_when_it_is_ticked_on_their_role(): void
    {
        Storage::disk('local')->put('routers/02GAM.ovpn', 'client');
        $site = $this->site();
        ChargingStation::factory()->for($site)->create(['password' => 'Station-Secret']);
        $router = Router::factory()->for($site)->create(['password' => 'Router-Secret', 'vpn_file' => 'routers/02GAM.ovpn', 'vpn_file_name' => '02GAM.ovpn']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // Charging station secrets: administrators have every normal permission.
        $this->get('/charging-stations/'.ChargingStation::sole()->id)->assertOk()->assertSee('Station-Secret');

        $this->get("/routers/{$router->id}")->assertOk()->assertDontSee('Router-Secret')->assertDontSee('02GAM.ovpn');
        $this->get(route('routers.vpn', $router))->assertForbidden();
        $this->assertFalse($admin->hasPermission('routers.secrets'));
        $this->assertTrue($admin->hasPermission('routers.update'));

        Filament::setCurrentPanel('admin');
        Livewire::test(EditRole::class, ['record' => $admin->role->getRouteKey()])
            ->assertSee(__('erp.roles.explicit_permissions'))
            ->fillForm(['permissions' => ['routers' => ['secrets']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['routers' => ['secrets']], $admin->role->fresh()->permissions);
        Filament::setCurrentPanel('app');
        $admin->refresh();
        $this->get("/routers/{$router->id}")->assertOk()->assertSee('Router-Secret');
        $this->get(route('routers.vpn', $router))->assertOk()->assertDownload('02GAM.ovpn');

        // Only the explicit permissions are stored on an administrator role.
        $role = Role::factory()->create(['is_admin' => true, 'permissions' => ['vehicles' => ['view'], 'routers' => ['view', 'secrets']]]);
        $this->assertSame(['routers' => ['secrets']], $role->permissions);
    }
}
