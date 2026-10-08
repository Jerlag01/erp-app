<?php

namespace Tests\Feature;

use App\Enums\WasteRegion;
use App\Filament\Pages\WasteTotalsOverview;
use App\Filament\Resources\WasteCategories\Pages\ManageWasteCategories;
use App\Filament\Resources\WasteEntries\Pages\ManageWasteEntries;
use App\Http\Controllers\WastePdfController;
use App\Models\User;
use App\Models\WasteCategory;
use App\Models\WasteEntry;
use App\Models\WasteProcessor;
use App\Models\WorkSite;
use App\Support\WasteTotals;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WasteTest extends TestCase
{
    use RefreshDatabase;

    protected function registrar(): User
    {
        $user = User::factory()->withPermissions(['waste' => ['view', 'create', 'update', 'delete']])->inTeam()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel('app');

        return $user;
    }

    protected function category(string $code): WasteCategory
    {
        return WasteCategory::query()->where('waste_code', $code)->sole();
    }

    #[Test]
    public function the_categories_come_with_their_waste_codes(): void
    {
        $lead = $this->category('16 06 01*');

        $this->assertSame('Batterijen', $lead->parent->name);
        $this->assertTrue($lead->isBatteries());
        $this->assertTrue($lead->isHazardous());
        $this->assertFalse($this->category('17 04 01')->isBatteries());
    }

    #[Test]
    public function an_entry_is_registered_with_date_weight_processor_and_reference(): void
    {
        $user = $this->registrar();
        $processor = WasteProcessor::factory()->create(['name' => 'Recupel Metaal']);

        Livewire::test(ManageWasteEntries::class)
            ->callAction('create', [
                'date' => '2026-10-05',
                'waste_category_id' => $this->category('17 04 01')->id,
                'weight_kg' => 125.5,
                'waste_processor_id' => $processor->id,
                'processor_reference' => 'VB-2026-0042',
                // Battery fields are ignored for other waste.
                'destruction_certificate' => '123',
            ])
            ->assertHasNoActionErrors();

        $entry = WasteEntry::sole();
        $this->assertSame('125.50', $entry->weight_kg);
        $this->assertSame('VB-2026-0042', $entry->processor_reference);
        $this->assertTrue($entry->creator->is($user));
        $this->assertNull($entry->destruction_certificate);
        $this->assertNull($entry->region);
    }

    #[Test]
    public function batteries_need_a_region_and_a_three_digit_certificate_while_po_and_cow_code_are_optional(): void
    {
        $this->registrar();
        $site = WorkSite::factory()->create(['cow_code' => '02GAM']);
        $data = [
            'date' => '2026-10-05',
            'waste_category_id' => $this->category('16 06 01*')->id,
            'weight_kg' => 40,
            'waste_processor_id' => WasteProcessor::factory()->create()->id,
        ];

        Livewire::test(ManageWasteEntries::class)
            ->callAction('create', [...$data, 'destruction_certificate' => '12'])
            ->assertHasActionErrors(['region' => 'required', 'destruction_certificate']);

        Livewire::test(ManageWasteEntries::class)
            ->callAction('create', [...$data, 'region' => 'brussels', 'destruction_certificate' => '007'])
            ->assertHasNoActionErrors();

        Livewire::test(ManageWasteEntries::class)
            ->callAction('create', [...$data, 'region' => 'flanders', 'destruction_certificate' => '008', 'work_site_id' => $site->id, 'po_number' => '4500123456'])
            ->assertHasNoActionErrors();

        $this->assertSame([null, '02GAM'], WasteEntry::query()->orderBy('id')->pluck('cow_code')->all());
        $this->assertSame('4500123456', WasteEntry::query()->latest('id')->value('po_number'));
    }

    #[Test]
    public function each_region_has_its_own_battery_list(): void
    {
        $flanders = WasteEntry::factory()->batteries(WasteRegion::Flanders)->create();
        $wallonia = WasteEntry::factory()->batteries(WasteRegion::Wallonia)->create();
        $copper = WasteEntry::factory()->create();
        $this->registrar();

        Livewire::test(ManageWasteEntries::class)
            ->assertCanSeeTableRecords([$flanders, $wallonia, $copper])
            ->set('activeTab', 'flanders')
            ->assertCanSeeTableRecords([$flanders])
            ->assertCanNotSeeTableRecords([$wallonia, $copper])
            ->assertTableColumnVisible('destruction_certificate')
            ->assertTableColumnHidden('processor.name')
            ->set('activeTab', 'wallonia')
            ->assertCanSeeTableRecords([$wallonia])
            ->assertCanNotSeeTableRecords([$flanders, $copper]);
    }

    #[Test]
    public function the_lists_and_the_totals_are_exported_to_pdf(): void
    {
        $entries = WasteEntry::factory()->batteries(WasteRegion::Brussels)->count(2)->create();
        $user = $this->registrar();

        $url = WastePdfController::urlFor('brussels', $entries->modelKeys(), ['from' => '2026-01-01']);
        $response = $this->get($url)->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('brussel', (string) $response->headers->get('Content-Disposition'));

        // The token belongs to the user who made it.
        $this->actingAs(User::factory()->withPermissions(['waste' => ['view']])->inTeam()->create())->get($url)->assertNotFound();
        $this->actingAs($user);

        Livewire::test(ManageWasteEntries::class)->set('activeTab', 'brussels')->callAction('exportPdf')->assertHasNoActionErrors();

        $totals = $this->get(route('waste.totals-pdf', ['from' => '2026-01-01', 'until' => '2026-12-31']))->assertOk();
        $this->assertStringStartsWith('%PDF', $totals->getContent());
    }

    #[Test]
    public function the_totals_add_up_per_category_and_waste_code(): void
    {
        WasteEntry::factory()->create(['waste_category_id' => $this->category('17 04 01')->id, 'weight_kg' => 100, 'date' => '2026-03-01']);
        WasteEntry::factory()->create(['waste_category_id' => $this->category('17 04 01')->id, 'weight_kg' => 50.25, 'date' => '2026-06-01']);
        WasteEntry::factory()->create(['waste_category_id' => $this->category('17 04 02')->id, 'weight_kg' => 20, 'date' => '2026-06-01']);
        WasteEntry::factory()->create(['waste_category_id' => $this->category('17 04 01')->id, 'weight_kg' => 999, 'date' => '2025-12-31']);

        $totals = WasteTotals::between('2026-01-01', '2026-12-31');
        $metals = collect($totals['groups'])->first(fn (array $group): bool => $group['category']->name === 'Metalen');
        $copper = collect($metals['rows'])->first(fn (array $row): bool => $row['category']->waste_code === '17 04 01');

        $this->assertEqualsWithDelta(150.25, $copper['kg'], 0.001);
        $this->assertSame(2, $copper['count']);
        $this->assertEqualsWithDelta(170.25, $metals['kg'], 0.001);
        $this->assertEqualsWithDelta(170.25, $totals['kg'], 0.001);

        $this->registrar();
        Livewire::test(WasteTotalsOverview::class)
            ->assertSee('17 04 01')
            ->assertSee('150,25 kg')
            ->call('setYear', 2025)
            ->assertSee('999,00 kg');
    }

    #[Test]
    public function employees_need_the_permission_to_register_waste(): void
    {
        $viewer = User::factory()->withPermissions(['waste' => ['view']])->inTeam()->create();
        $nobody = User::factory()->inTeam()->create();

        $this->actingAs($nobody)->get('/waste-registry')->assertForbidden();
        $this->actingAs($nobody)->get('/waste-totals')->assertForbidden();

        $this->actingAs($viewer)->get('/waste-registry')->assertOk();
        $this->actingAs($viewer)->get('/waste-totals')->assertOk();
        $this->actingAs($viewer)->get('/waste-categories')->assertForbidden();
        Filament::setCurrentPanel('app');
        Livewire::test(ManageWasteEntries::class)->assertActionHidden('create');
    }

    #[Test]
    public function categories_in_use_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $used = $this->category('17 04 01');
        WasteEntry::factory()->create(['waste_category_id' => $used->id]);
        $unused = $this->category('17 04 02');
        $this->actingAs($admin);
        Filament::setCurrentPanel('app');

        Livewire::test(ManageWasteCategories::class)
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($used))
            ->callAction(TestAction::make(DeleteAction::class)->table($unused));

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    #[Test]
    public function the_totals_are_also_on_the_admin_side(): void
    {
        WasteEntry::factory()->create(['waste_category_id' => $this->category('17 04 01')->id, 'weight_kg' => 42, 'date' => now()->toDateString()]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/waste-totals')->assertOk()->assertSee('17 04 01')->assertSee('42,00 kg');
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('/admin/waste-totals', escape: false);
        $this->actingAs(User::factory()->withPermissions(['waste' => ['view']])->inTeam()->create())->get('/admin/waste-totals')->assertForbidden();
    }

    #[Test]
    public function the_module_can_be_switched_off(): void
    {
        $this->setModules(['waste' => false]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/waste-registry')->assertForbidden();
        $this->actingAs($admin)->get('/admin/waste-totals')->assertForbidden();
        $this->actingAs($admin)->get('/waste-totals')->assertForbidden();
        $this->actingAs($admin)->get('/waste-processors')->assertForbidden();
        $this->actingAs($admin)->get(route('waste.totals-pdf'))->assertForbidden();
    }
}
