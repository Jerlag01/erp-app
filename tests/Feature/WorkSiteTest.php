<?php

namespace Tests\Feature;

use App\Enums\BuildingType;
use App\Filament\Resources\WorkSites\Pages\CreateWorkSite;
use App\Filament\Resources\WorkSites\Pages\EditWorkSite;
use App\Filament\Resources\WorkSites\Pages\ListWorkSites;
use App\Filament\Resources\WorkSites\Pages\ViewWorkSite;
use App\Filament\Resources\WorkSites\RelationManagers\RemarksRelationManager;
use App\Models\Country;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteArea;
use App\Models\WorkSiteContact;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        return $admin;
    }

    #[Test]
    public function countries_are_available_in_every_language_with_belgium_first_choice(): void
    {
        $this->assertGreaterThan(240, Country::count());
        $belgium = Country::where('code', 'BE')->sole();

        $this->assertSame('België', $belgium->localName('nl'));
        $this->assertSame('Belgique', $belgium->localName('fr'));
        $this->assertSame($belgium->id, Country::belgiumId());

        app()->setLocale('nl');
        $this->assertContains('Duitsland', Country::options());
    }

    #[Test]
    public function a_work_site_is_created_with_a_valid_cow_code_and_its_first_type_is_logged(): void
    {
        $this->admin();
        $area = WorkSiteArea::factory()->create();

        Livewire::test(CreateWorkSite::class)
            ->fillForm(['cow_code' => '2GAMX', 'building_type' => 'atmk'])
            ->call('create')
            ->assertHasFormErrors(['cow_code' => 'regex']);

        Livewire::test(CreateWorkSite::class)
            ->fillForm([
                'cow_code' => '02gam',
                'building_type' => 'ldc',
                'street' => 'Rue de la Gare',
                'house_number' => '5',
                'addition' => 'bus 2',
                'postal_code' => '4000',
                'city' => 'Liège',
                'state' => 'Liège',
                'work_site_area_id' => $area->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $site = WorkSite::sole();
        $this->assertSame('02GAM', $site->cow_code);
        $this->assertSame(Country::belgiumId(), $site->country_id);
        $this->assertSame('Rue de la Gare 5 bus 2, 4000 Liège, België', $site->addressLine());
        $this->assertSame(BuildingType::Ldc, $site->typeChanges()->sole()->to_type);
        $this->assertNull($site->typeChanges()->sole()->from_type);

        Livewire::test(CreateWorkSite::class)
            ->fillForm(['cow_code' => '02GAM', 'building_type' => 'ec'])
            ->call('create')
            ->assertHasFormErrors(['cow_code' => 'unique']);
    }

    #[Test]
    public function the_building_type_changes_through_its_own_form_and_is_logged(): void
    {
        $admin = $this->admin();
        $site = WorkSite::factory()->create(['building_type' => BuildingType::Atmk]);

        Livewire::test(EditWorkSite::class, ['record' => $site->getRouteKey()])
            ->assertFormFieldHidden('building_type');

        Livewire::test(ViewWorkSite::class, ['record' => $site->getRouteKey()])
            ->callAction('changeBuildingType', [
                'building_type' => 'decommissioned',
                'changed_on' => '2026-10-01',
                'reason' => 'Site afgebroken',
                'remarks' => 'Materiaal teruggebracht naar depot',
            ])
            ->assertHasNoActionErrors();

        $site->refresh();
        $this->assertSame(BuildingType::Decommissioned, $site->building_type);
        $this->assertTrue($site->isDecommissioned());

        $change = $site->typeChanges()->sole();
        $this->assertSame(BuildingType::Atmk, $change->from_type);
        $this->assertSame(BuildingType::Decommissioned, $change->to_type);
        $this->assertSame('2026-10-01', $change->changed_on->toDateString());
        $this->assertSame('Site afgebroken', $change->reason);
        $this->assertTrue($change->user->is($admin));
    }

    #[Test]
    public function decommissioned_cow_codes_are_shown_in_red_and_types_are_visible_in_the_overview(): void
    {
        $this->admin();
        $active = WorkSite::factory()->create(['cow_code' => '01ACT', 'building_type' => BuildingType::Colibri]);
        $gone = WorkSite::factory()->create(['cow_code' => '01OFF', 'building_type' => BuildingType::Decommissioned]);

        $html = $this->get('/admin/work-sites')->assertOk()->assertSee('COLIBRI')->assertSee('01OFF')->getContent();
        $this->assertSame(1, substr_count($html, 'data-decommissioned="true"'));

        Livewire::test(ListWorkSites::class)
            ->assertTableColumnStateSet('building_type', BuildingType::Colibri, $active)
            ->assertTableColumnStateSet('building_type', BuildingType::Decommissioned, $gone);
    }

    #[Test]
    public function the_list_is_searched_and_filtered_on_the_cow_code(): void
    {
        $this->admin();
        $a = WorkSite::factory()->create(['cow_code' => '02GAM']);
        $b = WorkSite::factory()->create(['cow_code' => '02HAS']);
        $c = WorkSite::factory()->create(['cow_code' => '15LUI']);

        Livewire::test(ListWorkSites::class)
            ->searchTable('02GA')
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b, $c])
            ->searchTable(null)
            ->filterTable('cow_code', ['cow_code' => '02'])
            ->assertCanSeeTableRecords([$a, $b])
            ->assertCanNotSeeTableRecords([$c])
            ->resetTableFilters()
            ->filterTable('building_type', [BuildingType::Atmk->value])
            ->assertCanSeeTableRecords([$a, $b, $c]);
    }

    #[Test]
    public function waze_and_google_maps_links_search_the_address(): void
    {
        $site = WorkSite::factory()->create(['street' => 'Rue de la Gare', 'house_number' => '5', 'addition' => 'bus 2', 'postal_code' => '4000', 'city' => 'Liège']);

        $this->assertSame('https://waze.com/ul?q=Rue%20de%20la%20Gare%205%2C%204000%20Li%C3%A8ge%2C%20Belgium&navigate=yes', $site->wazeUrl());
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=Rue%20de%20la%20Gare%205%2C%204000%20Li%C3%A8ge%2C%20Belgium', $site->googleMapsUrl());
        $this->assertNull(WorkSite::factory()->create(['street' => null, 'house_number' => null, 'postal_code' => null, 'city' => null, 'country_id' => null])->wazeUrl());

        $this->admin();
        $this->get("/admin/work-sites/{$site->id}")->assertSee(e($site->wazeUrl()), escape: false)->assertSee(e($site->googleMapsUrl()), escape: false);
    }

    #[Test]
    public function a_site_without_its_own_contact_uses_the_contact_of_its_area(): void
    {
        $areaContact = WorkSiteContact::factory()->create(['first_name' => 'Anna', 'last_name' => 'Area']);
        $siteContact = WorkSiteContact::factory()->create(['first_name' => 'Sam', 'last_name' => 'Site']);
        $area = WorkSiteArea::factory()->create(['work_site_contact_id' => $areaContact->id]);

        $withOwn = WorkSite::factory()->for($area, 'area')->create(['work_site_contact_id' => $siteContact->id]);
        $withoutOwn = WorkSite::factory()->for($area, 'area')->create();

        $this->assertTrue($withOwn->effectiveContact()->is($siteContact));
        $this->assertTrue($withoutOwn->effectiveContact()->is($areaContact));
    }

    #[Test]
    public function remarks_are_added_and_removed_with_their_own_permission(): void
    {
        $site = WorkSite::factory()->create();
        $writer = User::factory()->withPermissions(['work_sites' => ['view', 'remarks']])->create();
        $reader = User::factory()->withPermissions(['work_sites' => ['view']])->create();
        $this->setModules(['teams' => false]);
        Filament::setCurrentPanel('app');

        Livewire::actingAs($reader)
            ->test(RemarksRelationManager::class, ['ownerRecord' => $site, 'pageClass' => ViewWorkSite::class])
            ->assertTableActionHidden('create');

        Livewire::actingAs($writer)
            ->test(RemarksRelationManager::class, ['ownerRecord' => $site, 'pageClass' => ViewWorkSite::class])
            ->callTableAction('create', data: ['body' => 'Sleutel ligt bij de buren (nr. 7).'])
            ->assertHasNoTableActionErrors();

        $remark = $site->remarks()->sole();
        $this->assertTrue($remark->user->is($writer));

        Livewire::actingAs($writer)
            ->test(RemarksRelationManager::class, ['ownerRecord' => $site, 'pageClass' => ViewWorkSite::class])
            ->callTableAction('delete', $remark);

        $this->assertSame(0, $site->remarks()->count());
    }

    #[Test]
    public function changing_the_type_needs_its_own_permission(): void
    {
        $site = WorkSite::factory()->create();
        $editor = User::factory()->withPermissions(['work_sites' => ['view', 'update']])->create();
        $changer = User::factory()->withPermissions(['work_sites' => ['view', 'change_type']])->create();
        $this->setModules(['teams' => false]);

        $this->assertFalse($editor->can('changeType', $site));
        $this->assertTrue($changer->can('changeType', $site));
    }

    #[Test]
    public function areas_and_contacts_in_use_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $contact = WorkSiteContact::factory()->create();
        $area = WorkSiteArea::factory()->create(['work_site_contact_id' => $contact->id]);
        WorkSite::factory()->for($area, 'area')->create();

        $this->assertFalse($admin->can('delete', $area));
        $this->assertFalse($admin->can('delete', $contact));
        $this->assertTrue($admin->can('delete', WorkSiteArea::factory()->create()));
        $this->assertTrue($admin->can('delete', WorkSiteContact::factory()->create()));
    }

    #[Test]
    public function the_module_can_be_switched_off(): void
    {
        $admin = User::factory()->admin()->create();
        $site = WorkSite::factory()->create();

        $this->setModules(['work_sites' => false]);

        foreach (['/admin/work-sites', "/admin/work-sites/{$site->id}", '/admin/work-site-areas', '/admin/work-site-contacts'] as $url) {
            $this->actingAs($admin)->get($url)->assertForbidden();
        }
    }

    #[Test]
    public function every_work_site_page_renders(): void
    {
        $admin = User::factory()->admin()->create();
        $area = WorkSiteArea::factory()->create(['work_site_contact_id' => WorkSiteContact::factory()->create()->id]);
        $site = WorkSite::factory()->for($area, 'area')->create(['images' => ['work-sites/a.jpg']]);
        $site->changeBuildingType(BuildingType::Ec, '2026-10-01', 'Upgrade');
        $site->remarks()->create(['body' => 'Toegang via achterpoort']);

        foreach (['/admin', ''] as $prefix) {
            foreach (['/work-sites', '/work-sites/create', "/work-sites/{$site->id}", "/work-sites/{$site->id}/edit", '/work-site-areas', '/work-site-contacts'] as $url) {
                $this->actingAs($admin)->get($prefix.$url)->assertOk();
            }
        }
    }
}
