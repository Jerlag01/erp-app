<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Filament\Admin\Resources\ClientUsers\Pages\ManageClientUsers;
use App\Filament\Client\Resources\BatteryDeliveries\ClientBatteryDeliveryResource;
use App\Filament\Client\Resources\BatteryDeliveries\Pages\ListClientBatteryDeliveries;
use App\Filament\Client\Resources\ChargingStations\ClientChargingStationResource;
use App\Filament\Client\Resources\ChargingStations\Pages\ListClientChargingStations;
use App\Filament\Client\Resources\Projects\ClientProjectResource;
use App\Filament\Client\Resources\Projects\Pages\ListClientProjects;
use App\Filament\Client\Resources\Routers\ClientRouterResource;
use App\Filament\Client\Resources\Routers\Pages\ListClientRouters;
use App\Models\BatteryDelivery;
use App\Models\ChargingStation;
use App\Models\ClientUser;
use App\Models\Project;
use App\Models\Router;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\ClientPortal;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function client(array $permissions, array $categories = ClientPortal::CATEGORIES): ClientUser
    {
        $client = ClientUser::factory()->withPermissions($permissions)->create(['project_categories' => $categories]);
        $this->actingAs($client, ClientPortal::GUARD);
        Filament::setCurrentPanel(ClientPortal::PANEL);

        return $client;
    }

    #[Test]
    public function client_users_and_erp_users_stay_apart(): void
    {
        $client = ClientUser::factory()->create(['email' => 'manager@proximus.example', 'password' => 'Geheim-wachtwoord-1']);
        $staff = User::factory()->admin()->create(['email' => 'admin@power.example', 'password' => 'Ander-wachtwoord-1']);

        // A signed-in ERP administrator is not signed in on the portal, and the reverse.
        $this->actingAs($staff)->get('/client')->assertRedirect('/client/login');
        $this->actingAs($client, ClientPortal::GUARD)->get('/client')->assertOk();
        auth()->guard('web')->logout();
        $this->get('/admin')->assertRedirect();
        $this->get('/')->assertRedirect();

        // ERP credentials do not open the portal; inactive client users cannot get in.
        $this->assertFalse(auth()->guard(ClientPortal::GUARD)->validate(['email' => 'admin@power.example', 'password' => 'Ander-wachtwoord-1']));
        $this->assertTrue(auth()->guard(ClientPortal::GUARD)->validate(['email' => 'manager@proximus.example', 'password' => 'Geheim-wachtwoord-1']));
        $client->update(['is_active' => false]);
        $this->assertFalse($client->fresh()->canAccessPanel(Filament::getPanel(ClientPortal::PANEL)));
    }

    #[Test]
    public function the_project_overview_shows_only_the_allowed_categories_with_po_numbers(): void
    {
        $site = WorkSite::factory()->create(['cow_code' => '02GAM']);
        $battery = Project::factory()->inCategory('604')->for($site)->create(['status' => ProjectStatus::InProgress]);
        $battery->poNumbers()->create(['number' => '4500123456']);
        $enersys = Project::factory()->inCategory('605')->for($site)->create();
        $private = Project::factory()->inCategory('606')->create(['client_name' => 'Familie Peeters']);
        $this->client([ClientPortal::PROJECTS], ['604']);

        Livewire::test(ListClientProjects::class)
            ->assertCanSeeTableRecords([$battery])
            ->assertCanNotSeeTableRecords([$enersys, $private])
            ->assertSee('02GAM')
            ->assertSee('4500123456')
            ->assertSee(ProjectStatus::InProgress->getLabel());

        // Nothing to change: no create, edit or delete pages.
        $this->assertFalse(ClientProjectResource::canCreate());
        $this->assertFalse(ClientProjectResource::canEdit($battery));
        $this->assertFalse(ClientProjectResource::canDelete($battery));
    }

    #[Test]
    public function battery_deliveries_never_show_their_cost(): void
    {
        $delivery = BatteryDelivery::factory()->create(['total_cost' => 98765.43, 'notes' => 'Prijs per cel 123,45', 'order_confirmation' => '4141234']);
        $this->client([ClientPortal::BATTERY_DELIVERIES]);

        Livewire::test(ListClientBatteryDeliveries::class)
            ->assertCanSeeTableRecords([$delivery])
            ->assertSee('4141234')
            ->assertDontSee('98765')
            ->assertDontSee('98.765')
            ->assertDontSee('123,45');

        // Without the permission the overview is closed.
        $this->client([ClientPortal::PROJECTS]);
        $this->get(ClientBatteryDeliveryResource::getUrl())->assertForbidden();
    }

    #[Test]
    public function passwords_and_vpn_files_need_their_own_permission(): void
    {
        Storage::fake('local');
        $station = ChargingStation::factory()->create(['password' => 'Laadpaal-Geheim', 'recovery_code' => 'HERSTEL-123']);
        Storage::disk('local')->put('routers/vpn.ovpn', 'client');
        $router = Router::factory()->create(['password' => 'Router-Geheim', 'vpn_file' => 'routers/vpn.ovpn', 'vpn_file_name' => 'site.ovpn']);

        $this->client([ClientPortal::CHARGING_STATIONS, ClientPortal::ROUTERS]);
        Livewire::test(ListClientChargingStations::class)
            ->assertCanSeeTableRecords([$station])
            ->assertDontSee('Laadpaal-Geheim')
            ->assertDontSee('HERSTEL-123');
        Livewire::test(ListClientRouters::class)
            ->assertCanSeeTableRecords([$router])
            ->assertDontSee('Router-Geheim')
            ->assertTableActionHidden('vpn', $router);
        $this->get(route('client.routers.vpn', $router))->assertForbidden();

        $this->client([ClientPortal::CHARGING_STATIONS, ClientPortal::CHARGING_SECRETS, ClientPortal::ROUTERS, ClientPortal::ROUTER_SECRETS]);
        Livewire::test(ListClientChargingStations::class)->assertSee('Laadpaal-Geheim')->assertSee('HERSTEL-123');
        Livewire::test(ListClientRouters::class)->assertSee('Router-Geheim')->assertTableActionVisible('vpn', $router);
        $this->get(route('client.routers.vpn', $router))->assertOk()->assertDownload('site.ovpn');

        // An ERP session does not open the client download.
        auth()->guard(ClientPortal::GUARD)->logout();
        $this->actingAs(User::factory()->admin()->create())->get(route('client.routers.vpn', $router))->assertForbidden();
    }

    #[Test]
    public function only_what_is_ticked_shows_in_the_navigation_and_start_page(): void
    {
        $this->client([ClientPortal::PROJECTS]);

        $this->get('/client')->assertOk()->assertSee(__('erp.client_portal.projects'))->assertDontSee(__('erp.client_portal.routers'));
        $this->get(ClientProjectResource::getUrl())->assertOk();
        $this->get(ClientChargingStationResource::getUrl())->assertForbidden();
        $this->get(ClientRouterResource::getUrl())->assertForbidden();

        // A switched-off module closes its overview too.
        $this->setModules(['projects' => false]);
        $this->get(ClientProjectResource::getUrl())->assertForbidden();
    }

    #[Test]
    public function administrators_manage_the_portal_users(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(ManageClientUsers::class)
            ->callAction('create', [
                'name' => 'Proximus Manager',
                'email' => 'pm@proximus.example',
                'company' => 'Proximus',
                'locale' => 'fr',
                'password' => 'Een-lang-wachtwoord',
                'is_active' => true,
                'permissions' => [ClientPortal::PROJECTS, ClientPortal::ROUTERS],
                'project_categories' => ['604', '610'],
            ])
            ->assertHasNoActionErrors();

        $client = ClientUser::query()->sole();
        $this->assertTrue(Hash::check('Een-lang-wachtwoord', $client->password));
        $this->assertSame([ClientPortal::PROJECTS, ClientPortal::ROUTERS], $client->permissions);
        $this->assertSame(['604', '610'], $client->projectCategories());

        // Whatever is sent, only known permissions and the portal's categories are stored.
        $client->update(['permissions' => [ClientPortal::PROJECTS, 'admin'], 'project_categories' => ['604', '606']]);
        $this->assertSame([ClientPortal::PROJECTS], $client->fresh()->permissions);
        $this->assertSame(['604'], $client->fresh()->project_categories);

        // The password link leads to the portal's own reset page.
        Livewire::test(ManageClientUsers::class)->callTableAction('send_reset', $client);
        Notification::assertSentTo($client, ResetPassword::class, fn (ResetPassword $notification): bool => str_contains($notification->url, '/client/password-reset/reset'));

        // Only administrators manage them.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view']])->inTeam()->create());
        $this->get(route('filament.admin.resources.client-users.index'))->assertForbidden();
    }
}
