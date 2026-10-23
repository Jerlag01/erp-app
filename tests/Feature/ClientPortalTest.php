<?php

namespace Tests\Feature;

use App\Enums\MailTemplateKey;
use App\Enums\ProjectStatus;
use App\Enums\SiteLocationType;
use App\Filament\Admin\Resources\ClientUsers\ClientUserResource;
use App\Filament\Admin\Resources\ClientUsers\Pages\ManageClientUsers;
use App\Filament\Client\Pages\EditClientProfile;
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
use App\Models\MailTemplate;
use App\Models\Project;
use App\Models\Router;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteArea;
use App\Models\WorkSiteContact;
use App\Notifications\Client\ClientResetPassword;
use App\Notifications\Client\ClientWelcome;
use App\Support\ClientPortal;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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
        $this->client([ClientPortal::PROJECTS, ClientPortal::ALL_PROJECTS], ['604']);

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
        $this->client([ClientPortal::BATTERY_DELIVERIES, ClientPortal::ALL_PROJECTS]);

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

        $this->client([ClientPortal::CHARGING_STATIONS, ClientPortal::ROUTERS, ClientPortal::ALL_PROJECTS]);
        Livewire::test(ListClientChargingStations::class)
            ->assertCanSeeTableRecords([$station])
            ->assertDontSee('Laadpaal-Geheim')
            ->assertDontSee('HERSTEL-123');
        Livewire::test(ListClientRouters::class)
            ->assertCanSeeTableRecords([$router])
            ->assertDontSee('Router-Geheim')
            ->assertTableActionHidden('vpn', $router);
        $this->get(route('client.routers.vpn', $router))->assertForbidden();

        $this->client([ClientPortal::CHARGING_STATIONS, ClientPortal::CHARGING_SECRETS, ClientPortal::ROUTERS, ClientPortal::ROUTER_SECRETS, ClientPortal::ALL_PROJECTS]);
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
                'send_welcome_mail' => false,
                'password' => 'Een-lang-wachtwoord',
                'is_active' => true,
                'permissions' => [ClientPortal::PROJECTS, ClientPortal::ROUTERS],
                'project_categories' => ['604', '610'],
            ])
            ->assertHasNoActionErrors();

        $client = ClientUser::query()->sole();
        Notification::assertNothingSentTo($client);
        $this->assertTrue(Hash::check('Een-lang-wachtwoord', $client->password));
        $this->assertSame([ClientPortal::PROJECTS, ClientPortal::ROUTERS], $client->permissions);
        $this->assertSame(['604', '610'], $client->projectCategories());

        // Whatever is sent, only known permissions and the portal's categories are stored.
        $client->update(['permissions' => [ClientPortal::PROJECTS, 'admin'], 'project_categories' => ['604', '606']]);
        $this->assertSame([ClientPortal::PROJECTS], $client->fresh()->permissions);
        $this->assertSame(['604'], $client->fresh()->project_categories);

        // The password link leads to the portal's own reset page.
        Livewire::test(ManageClientUsers::class)->callTableAction('send_reset', $client);
        Notification::assertSentTo($client, ClientResetPassword::class, fn (ClientResetPassword $notification): bool => str_contains($notification->toMail($client)->rendered['html'], '/client/password-reset/reset'));

        // Only administrators manage them.
        $this->actingAs(User::factory()->withPermissions(['projects' => ['view']])->inTeam()->create());
        $this->get(route('filament.admin.resources.client-users.index'))->assertForbidden();
    }

    #[Test]
    public function a_new_client_user_gets_a_welcome_mail_in_their_language(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        // Without a password: the welcome mail lets the user choose one.
        Livewire::test(ManageClientUsers::class)
            ->callAction('create', [
                'name' => 'Sophie Dubois',
                'email' => 'sophie@proximus.example',
                'company' => 'Proximus',
                'locale' => 'fr',
                'send_welcome_mail' => true,
                'is_active' => true,
                'permissions' => [ClientPortal::PROJECTS, ClientPortal::BATTERY_DELIVERIES],
                'project_categories' => ['604', '605'],
            ])
            ->assertHasNoActionErrors();

        $client = ClientUser::query()->sole();
        $this->assertNotNull($client->welcome_sent_at);
        Notification::assertSentTo($client, ClientWelcome::class);

        $mail = (new ClientWelcome)->toMail($client);
        $this->assertSame('Votre accès à Global overview for Client', $mail->rendered['subject']);
        $this->assertStringContainsString('sophie@proximus.example', $mail->rendered['html']);
        $this->assertStringContainsString('Projets (604, 605)', $mail->rendered['html']);
        $this->assertStringContainsString('/client-welcome/', $mail->rendered['html']);

        // An edited template is used, in the language of the user.
        MailTemplate::query()->create(['key' => MailTemplateKey::ClientWelcome->value, 'locale' => 'en', 'subject' => 'Hi {name}', 'body' => 'Go to {portal_url}', 'is_active' => true]);
        $client->update(['locale' => 'en']);
        $this->assertSame('Hi Sophie Dubois', (new ClientWelcome)->toMail($client)->rendered['subject']);

        // The link opens the portal's "choose a password" page, until a password is set.
        $link = ClientWelcome::linkFor($client);
        auth()->logout();
        $this->get($link)->assertRedirectContains('/client/password-reset/reset');
        $this->get($link)->assertRedirectContains('/client/password-reset/reset');
        $client->update(['password' => 'Een-nieuw-wachtwoord']);
        $this->get($link)->assertRedirect(Filament::getPanel(ClientPortal::PANEL)->getLoginUrl());
        $this->get(URL::temporarySignedRoute('client.welcome', now()->subMinute(), ['clientUser' => $client->id, 'h' => ClientWelcome::passwordFingerprint($client)]))->assertForbidden();

        // Resending works from the list; an inactive user gets nothing.
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ManageClientUsers::class)->callTableAction('send_welcome', $client);
        Notification::assertSentToTimes($client, ClientWelcome::class, 2);
    }

    #[Test]
    public function the_forgot_password_mail_uses_the_editable_template(): void
    {
        $client = ClientUser::factory()->create(['locale' => 'nl', 'name' => 'Piet']);

        $mail = (new ClientResetPassword('token-123'))->toMail($client);

        $this->assertSame('Nieuw wachtwoord voor Global overview for Client', $mail->rendered['subject']);
        $this->assertStringContainsString('/client/password-reset/reset', $mail->rendered['html']);
        $this->assertStringContainsString('60 minuten', $mail->rendered['html']);
    }

    #[Test]
    public function a_client_sees_only_their_own_projects_unless_they_may_see_all(): void
    {
        $contact = WorkSiteContact::factory()->create(['first_name' => 'Marc', 'last_name' => 'Janssens', 'company' => 'Proximus']);
        $ownSite = WorkSite::factory()->create(['cow_code' => '02GAM', 'work_site_contact_id' => $contact->id]);
        $areaSite = WorkSite::factory()->create(['cow_code' => '02E23', 'work_site_contact_id' => null, 'work_site_area_id' => WorkSiteArea::factory()->hasAttached($contact, [], 'contacts')->create()->id]);
        $otherSite = WorkSite::factory()->create(['cow_code' => '03XYZ', 'work_site_contact_id' => WorkSiteContact::factory()->create()->id]);

        $atOwnSite = Project::factory()->inCategory('604')->for($ownSite)->create();
        $atAreaSite = Project::factory()->inCategory('610')->for($areaSite)->create();
        $assigned = Project::factory()->inCategory('620')->for($otherSite)->create();
        $otherClient = Project::factory()->inCategory('604')->for($otherSite)->create();

        $ownDelivery = BatteryDelivery::factory()->create(['project_id' => $atOwnSite->id]);
        $otherDelivery = BatteryDelivery::factory()->create(['project_id' => $otherClient->id]);
        $ownRouter = Router::factory()->create(['work_site_id' => $ownSite->id]);
        $otherRouter = Router::factory()->create(['work_site_id' => WorkSite::factory()->create()->id]);
        $ownStation = ChargingStation::factory()->create(['work_site_id' => $areaSite->id]);
        $otherStation = ChargingStation::factory()->create(['work_site_id' => WorkSite::factory()->create()->id]);

        $client = $this->client([ClientPortal::PROJECTS, ClientPortal::BATTERY_DELIVERIES, ClientPortal::CHARGING_STATIONS, ClientPortal::ROUTERS, ClientPortal::ROUTER_SECRETS]);
        $client->update(['work_site_contact_id' => $contact->id]);
        $client->projects()->attach($assigned);

        Livewire::test(ListClientProjects::class)
            ->assertCanSeeTableRecords([$atOwnSite, $atAreaSite, $assigned])
            ->assertCanNotSeeTableRecords([$otherClient]);
        Livewire::test(ListClientBatteryDeliveries::class)->assertCanSeeTableRecords([$ownDelivery])->assertCanNotSeeTableRecords([$otherDelivery]);
        // The assigned project's site counts as the client's own site too.
        Livewire::test(ListClientRouters::class)->assertCanSeeTableRecords([$ownRouter])->assertCanNotSeeTableRecords([$otherRouter]);
        Livewire::test(ListClientChargingStations::class)->assertCanSeeTableRecords([$ownStation])->assertCanNotSeeTableRecords([$otherStation]);

        // Someone else's VPN file stays closed, also through the direct link.
        Storage::fake('local');
        Storage::disk('local')->put('routers/other.ovpn', 'client');
        $otherRouter->update(['vpn_file' => 'routers/other.ovpn']);
        $this->get(route('client.routers.vpn', $otherRouter))->assertForbidden();

        // Without a contact or assigned projects there is nothing to see.
        $this->client([ClientPortal::PROJECTS]);
        Livewire::test(ListClientProjects::class)->assertCountTableRecords(0);

        // With "all projects" the other clients' projects show too, within the ticked categories.
        $client->update(['permissions' => [...$client->permissions, ClientPortal::ALL_PROJECTS], 'project_categories' => ['604']]);
        $this->actingAs($client->fresh(), ClientPortal::GUARD);
        Livewire::test(ListClientProjects::class)
            ->assertCanSeeTableRecords([$atOwnSite, $otherClient])
            ->assertCanNotSeeTableRecords([$atAreaSite, $assigned]);
    }

    #[Test]
    public function an_administrator_links_a_contact_person_and_projects_to_a_client_user(): void
    {
        Notification::fake();
        $contact = WorkSiteContact::factory()->create(['first_name' => 'Marc', 'last_name' => 'Janssens', 'company' => 'Proximus', 'email' => 'marc@proximus.example']);
        WorkSite::factory()->create(['cow_code' => '02GAM', 'work_site_contact_id' => $contact->id]);
        $project = Project::factory()->inCategory('604')->create();
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        // Found by COW code, and the contact's details fill the empty fields.
        $this->assertSame([$contact->id => 'Marc Janssens (Proximus)'], ClientUserResource::contactOptions('02GA'));
        $this->assertStringContainsString('02GAM', ClientUserResource::contactSitesText($contact->id));

        Livewire::test(ManageClientUsers::class)
            ->mountAction('create')
            ->fillForm(['work_site_contact_id' => $contact->id])
            ->assertSchemaStateSet(['name' => 'Marc Janssens', 'email' => 'marc@proximus.example', 'company' => 'Proximus'])
            ->fillForm(['locale' => 'nl', 'send_welcome_mail' => false, 'password' => 'Een-lang-wachtwoord', 'projects' => [$project->id], 'permissions' => [ClientPortal::PROJECTS]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $client = ClientUser::query()->sole();
        $this->assertSame($contact->id, $client->work_site_contact_id);
        $this->assertTrue($client->projects->contains($project));
        $this->assertFalse($client->seesAllProjects());
    }

    #[Test]
    public function charging_stations_without_a_cow_code_never_show_on_the_portal(): void
    {
        $atSite = ChargingStation::factory()->create();
        $thirdParty = ChargingStation::factory()->create(['location_type' => SiteLocationType::Other, 'client_company' => 'Fluvius', 'street' => 'Brusselsesteenweg', 'postal_code' => '9090', 'city' => 'Melle']);
        $this->assertNull($thirdParty->fresh()->work_site_id);

        // Not even with "all projects".
        $this->client([ClientPortal::CHARGING_STATIONS, ClientPortal::ROUTERS, ClientPortal::ALL_PROJECTS]);
        Livewire::test(ListClientChargingStations::class)
            ->assertCanSeeTableRecords([$atSite])
            ->assertCanNotSeeTableRecords([$thirdParty])
            ->assertDontSee('Fluvius');

        $this->get('/client')->assertOk();
        $this->assertSame(1, ClientChargingStationResource::getEloquentQuery()->count());
    }

    #[Test]
    public function a_client_removes_their_own_account_after_a_warning_and_their_password(): void
    {
        $admin = User::factory()->admin()->create();
        $client = ClientUser::factory()->create(['password' => 'Geheim-wachtwoord-1', 'name' => 'Ex Manager']);
        $project = Project::factory()->inCategory('604')->create();
        $client->projects()->attach($project);
        $this->actingAs($client, ClientPortal::GUARD);
        Filament::setCurrentPanel(ClientPortal::PANEL);

        $this->get('/client/profile')->assertOk()->assertSee(__('erp.client_portal.account_deleted.button'));

        // The warning must be accepted and the password must be right.
        Livewire::test(EditClientProfile::class)
            ->assertActionExists('deleteAccount', fn (Action $action): bool => $action->getModalDescription() === __('erp.client_portal.account_deleted.warning'))
            ->mountAction('deleteAccount')
            ->setActionData(['understood' => false, 'password' => 'fout'])
            ->callMountedAction()
            ->assertHasActionErrors(['understood', 'password']);
        $this->assertModelExists($client);

        Livewire::test(EditClientProfile::class)
            ->callAction('deleteAccount', ['understood' => true, 'password' => 'Geheim-wachtwoord-1'])
            ->assertHasNoActionErrors()
            ->assertRedirect('/client/login');

        $this->assertModelMissing($client);
        $this->assertDatabaseMissing('client_user_project', ['client_user_id' => $client->id]);
        $this->assertModelExists($project);
        $this->assertFalse(auth()->guard(ClientPortal::GUARD)->check());
        $this->assertSame(1, $admin->notifications()->count());
        $this->get('/client')->assertRedirect('/client/login');
    }
}
