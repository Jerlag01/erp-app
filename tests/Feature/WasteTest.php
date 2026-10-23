<?php

namespace Tests\Feature;

use App\Enums\WasteRegion;
use App\Filament\Pages\WasteTotalsOverview;
use App\Filament\Resources\WasteCategories\Pages\ManageWasteCategories;
use App\Filament\Resources\WasteEntries\Pages\ManageWasteEntries;
use App\Filament\Resources\WasteEntries\WasteEntryResource;
use App\Http\Controllers\WastePdfController;
use App\Models\User;
use App\Models\WasteCategory;
use App\Models\WasteEntry;
use App\Models\WasteEntryLine;
use App\Models\WasteProcessor;
use App\Models\WorkSite;
use App\Support\Eural;
use App\Support\WasteTotals;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WasteTest extends TestCase
{
    use RefreshDatabase;

    protected function registrar(): User
    {
        $user = User::factory()->withPermissions(['waste' => ['view', 'create', 'update', 'delete', 'generate_pdf']])->inTeam()->create();
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

        $this->assertSame(['16 06', "Batterijen en accu's"], [$lead->parent->waste_code, $lead->parent->name]);
        $this->assertTrue($lead->isBatteries());
        $this->assertTrue($lead->isHazardous());
        $this->assertFalse($this->category('17 04 01')->isBatteries());
        $this->assertTrue($this->category('20 01 33*')->isBatteries(), 'Collected batteries in chapter 20 are battery waste too.');
    }

    #[Test]
    public function the_complete_european_list_of_waste_is_available(): void
    {
        // Commission Decision 2014/955/EU: 111 subchapters and 842 codes, of which 408 hazardous.
        $this->assertSame(111, WasteCategory::query()->whereNull('parent_id')->whereNotNull('waste_code')->count());
        $this->assertSame(842, WasteCategory::query()->whereNotNull('parent_id')->count());
        $this->assertSame(408, WasteCategory::query()->whereNotNull('parent_id')->where('waste_code', 'like', '%*')->count());
        $this->assertSame(20, count(Eural::chapters()));

        // Existing categories kept their name under their subchapter.
        $this->assertSame('Kabels zonder gevaarlijke stoffen', $this->category('17 04 11')->name);
        $this->assertSame('17 04', $this->category('17 04 11')->parent->waste_code);
        $this->assertSame("Gescheiden ingezamelde elektrolyt uit batterijen en accu's", $this->category('16 06 06*')->name);
        $this->assertSame(0, WasteCategory::query()->whereNull('waste_code')->count(), 'The old groups without a code are gone.');

        $this->registrar();
        $this->assertArrayHasKey("Batterijen en accu's", WasteCategory::selectOptions());
        $this->get('/waste-categories')->assertForbidden();
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
            ])
            ->assertHasNoActionErrors();

        $entry = WasteEntry::sole();
        $this->assertSame('125.50', $entry->weight_kg);
        $this->assertSame('VB-2026-0042', $entry->processor_reference);
        $this->assertTrue($entry->creator->is($user));
        $this->assertSame(0, $entry->lines()->count(), 'Other waste has no battery lines.');
    }

    #[Test]
    public function battery_lines_need_a_region_a_three_digit_certificate_and_a_weight(): void
    {
        Repeater::fake();
        $this->registrar();
        $site = WorkSite::factory()->create(['cow_code' => '02GAM']);
        $data = [
            'date' => '2026-10-05',
            'waste_category_id' => $this->category('16 06 01*')->id,
            'waste_processor_id' => WasteProcessor::factory()->create()->id,
        ];

        Livewire::test(ManageWasteEntries::class)
            ->callAction('create', [...$data, 'lines' => [['destruction_certificate' => '12', 'weight_kg' => 10]]])
            ->assertHasActionErrors(['lines.0.region' => 'required', 'lines.0.destruction_certificate']);

        Livewire::test(ManageWasteEntries::class)
            ->callAction('create', [...$data, 'lines' => [['region' => 'brussels', 'destruction_certificate' => '007', 'weight_kg' => 40]]])
            ->assertHasNoActionErrors();

        Livewire::test(ManageWasteEntries::class)
            ->callAction('create', [...$data, 'lines' => [['region' => 'flanders', 'destruction_certificate' => '008', 'work_site_id' => $site->id, 'po_number' => '4500123456', 'weight_kg' => 12.5]]])
            ->assertHasNoActionErrors();

        $lines = WasteEntryLine::query()->orderBy('id')->get();
        $this->assertSame([null, '02GAM'], $lines->pluck('cow_code')->all());
        $this->assertSame('4500123456', $lines->last()->po_number);
        $this->assertSame(['40.00', '12.50'], WasteEntry::query()->orderBy('id')->pluck('weight_kg')->all());
    }

    #[Test]
    public function one_battery_entry_has_several_certificates_cow_codes_and_po_numbers_split_over_the_regions(): void
    {
        Repeater::fake();
        $this->registrar();
        $gam = WorkSite::factory()->create(['cow_code' => '02GAM']);
        $lie = WorkSite::factory()->create(['cow_code' => '04LIE']);

        Livewire::test(ManageWasteEntries::class)
            ->callAction('create', [
                'date' => '2026-10-05',
                'waste_category_id' => $this->category('16 06 01*')->id,
                'waste_processor_id' => WasteProcessor::factory()->create()->id,
                'lines' => [
                    ['region' => 'flanders', 'destruction_certificate' => '101', 'work_site_id' => $gam->id, 'po_number' => '4500000001', 'weight_kg' => 120.25],
                    ['region' => 'wallonia', 'destruction_certificate' => '102', 'work_site_id' => $lie->id, 'po_number' => '4500000002', 'weight_kg' => 80],
                    ['region' => 'flanders', 'destruction_certificate' => '103', 'work_site_id' => $gam->id, 'weight_kg' => 10],
                ],
            ])
            ->assertHasNoActionErrors();

        // The entry weighs the sum of its lines, also after a line changes.
        $entry = WasteEntry::sole();
        $this->assertSame('210.25', $entry->weight_kg);
        $entry->lines()->where('destruction_certificate', '103')->first()->update(['weight_kg' => 20]);
        $this->assertSame('220.25', $entry->fresh()->weight_kg);

        // Each regional list holds only its own lines and their weight.
        Livewire::test(ManageWasteEntries::class)
            ->set('activeTab', 'wallonia')
            ->assertCanSeeTableRecords([$entry])
            ->assertTableColumnStateSet('weight_kg', 80.0, $entry)
            ->assertTableColumnStateSet('certificates', ['102'], $entry)
            ->assertTableColumnStateSet('cow_codes', ['04LIE'], $entry)
            ->set('activeTab', 'flanders')
            ->assertTableColumnStateSet('weight_kg', 140.25, $entry)
            ->assertTableColumnStateSet('certificates', ['101', '103'], $entry)
            ->set('activeTab', 'all')
            ->assertTableColumnStateSet('weight_kg', 220.25, $entry)
            ->assertTableColumnStateSet('po_numbers', ['4500000001', '4500000002'], $entry);

        // The regional PDF lists the lines of that region.
        $locales = [];
        Event::listen('composing: waste.list-pdf', function ($view) use (&$locales): void {
            $locales[] = $view->getData()['rows']->map(fn ($row) => $row['line']->destruction_certificate)->all();
        });
        $this->get(WastePdfController::urlFor('flanders', [$entry->id]))->assertOk();
        $this->assertSame([['101', '103']], $locales);
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
            ->assertTableColumnVisible('certificates')
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
        $this->actingAs(User::factory()->withPermissions(['waste' => ['view', 'generate_pdf']])->inTeam()->create())->get($url)->assertNotFound();
        // Without "generate_pdf" no PDF at all.
        $this->actingAs(User::factory()->withPermissions(['waste' => ['view']])->inTeam()->create())->get($url)->assertForbidden();
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
        $metals = collect($totals['groups'])->first(fn (array $group): bool => $group['category']->waste_code === '17 04');
        $copper = collect($metals['rows'])->first(fn (array $row): bool => $row['category']->waste_code === '17 04 01');

        $this->assertEqualsWithDelta(150.25, $copper['kg'], 0.001);
        $this->assertSame(2, $copper['count']);
        $this->assertEqualsWithDelta(170.25, $metals['kg'], 0.001);
        $this->assertEqualsWithDelta(170.25, $totals['kg'], 0.001);

        // Grouped under the Eural chapters; only categories with entries unless the complete list is asked.
        $this->assertSame(['17'], array_map('strval', array_keys($totals['chapters'])));
        $this->assertSame('Bouw- en sloopafval (inclusief afgegraven grond van verontreinigde locaties)', $totals['chapters']['17']['title']);
        $this->assertEqualsWithDelta(170.25, $totals['chapters']['17']['kg'], 0.001);
        $this->assertCount(1, $totals['groups']);
        $this->assertCount(2, $metals['rows']);
        $complete = WasteTotals::between('2026-01-01', '2026-12-31', all: true);
        $this->assertCount(111, $complete['groups']);
        $this->assertCount(20, $complete['chapters']);
        $this->assertEqualsWithDelta(170.25, $complete['kg'], 0.001);

        $this->registrar();
        Livewire::test(WasteTotalsOverview::class)
            ->assertSee('17 04 01')
            ->assertSee('150,25 kg')
            ->assertDontSee('16 06 01*')
            ->set('all', true)
            ->assertSee('16 06 01*')
            ->assertSee('Loodaccu&#039;s', false)
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

    #[Test]
    public function entries_are_filtered_and_exported_per_year(): void
    {
        $this->registrar();
        $old = WasteEntry::factory()->create(['date' => '2025-05-10']);
        $new = WasteEntry::factory()->create(['date' => '2026-02-01']);

        $this->assertSame([2026 => '2026', 2025 => '2025'], array_slice(WasteEntryResource::yearOptions(), -2, null, true) + [], 'Newest first.');

        Livewire::test(ManageWasteEntries::class)
            ->filterTable('year', 2025)
            ->assertCanSeeTableRecords([$old])
            ->assertCanNotSeeTableRecords([$new]);

        // The export window proposes the filtered year; choosing a year exports all of that year.
        $page = Livewire::test(ManageWasteEntries::class)
            ->filterTable('year', 2026)
            ->mountAction('exportPdf')
            ->assertSchemaStateSet(['year' => 2026])
            ->setActionData(['year' => 2025])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame([$old->id], $this->exportedIds($page), 'The year chosen in the window wins over the list.');

        $page = Livewire::test(ManageWasteEntries::class)->callAction('exportPdf', ['year' => null]);
        $this->assertEqualsCanonicalizing([$old->id, $new->id], $this->exportedIds($page), 'Empty: the list as shown.');
    }

    /**
     * The entries of the PDF a page just opened (through its token).
     *
     * @return list<int>
     */
    protected function exportedIds(mixed $page): array
    {
        preg_match('#waste-pdf/([A-Za-z0-9]+)#', str_replace('\\', '', json_encode($page->effects['xjs'] ?? [], JSON_UNESCAPED_SLASHES)), $match);
        $this->assertNotEmpty($match, 'The PDF was opened.');

        return Cache::get("waste-pdf:{$match[1]}")['ids'];
    }
}
