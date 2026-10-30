<?php

namespace Tests\Feature;

use App\Actions\AdjustStock;
use App\Filament\Resources\StockItems\Pages\CreateStockItem;
use App\Filament\Resources\StockItems\Pages\ListStockItems;
use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\Distributor;
use App\Models\Location;
use App\Models\StockItem;
use App\Models\StockItemSupplier;
use App\Models\Unit;
use App\Models\User;
use App\Support\StockExport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockSuppliersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');
    }

    protected function rexel(): Distributor
    {
        return Distributor::factory()->create(['name' => 'Rexel', 'price_provider' => 'rexel', 'api_username' => 'WEB', 'api_password' => 'pw', 'api_customer_number' => '1']);
    }

    /**
     * @return list<list<mixed>>
     */
    protected function read(string $path): array
    {
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
    }

    #[Test]
    public function an_item_has_several_suppliers_with_their_own_reference_and_price(): void
    {
        $rexel = $this->rexel();
        $local = Distributor::factory()->create(['name' => 'Elektro Peeters']);
        $unit = Unit::factory()->create();

        Livewire::test(CreateStockItem::class)
            ->fillForm([
                'name_nl' => 'Automaat 16A',
                'unit_id' => $unit->id,
                'suppliers' => [
                    ['distributor_id' => $rexel->id, 'reference' => 'RX-16', 'is_preferred' => false],
                    ['distributor_id' => $local->id, 'reference' => 'P-16', 'price' => '7.25', 'is_preferred' => true],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = StockItem::query()->where('name', 'Automaat 16A')->sole();
        $this->assertSame(['Elektro Peeters', 'Rexel'], $item->suppliers()->with('distributor')->get()->pluck('distributor.name')->all());

        // The supplier without a connection: price by hand, which is also the item's (preferred) price.
        $manual = $item->suppliers()->where('distributor_id', $local->id)->sole();
        $this->assertSame('7.2500', $manual->price);
        $this->assertSame(StockItemSupplier::MANUAL, $manual->price_source);
        $this->assertSame('7.2500', $item->fresh()->market_price);
        $this->assertSame($local->id, $item->fresh()->distributor_id);
        $this->assertSame('P-16', $item->fresh()->distributor_reference);
        $this->assertSame($local->id, $item->prices()->sole()->distributor_id);

        // Rexel has a connection: no price by hand.
        $this->assertTrue($rexel->hasPriceConnection());
        $this->assertFalse($local->hasPriceConnection());
        $this->assertNull($item->suppliers()->where('distributor_id', $rexel->id)->sole()->price);

        // One supplier per distributor.
        Livewire::test(CreateStockItem::class)
            ->fillForm(['name_nl' => 'Dubbel', 'unit_id' => $unit->id, 'suppliers' => [['distributor_id' => $local->id], ['distributor_id' => $local->id]]])
            ->call('create')
            ->assertHasFormErrors();

        // Making another supplier preferred moves the item's price and reference along.
        $item->suppliers()->where('distributor_id', $rexel->id)->sole()->update(['is_preferred' => true, 'price' => 6.9, 'price_source' => 'rexel', 'price_updated_at' => now()]);
        $this->assertSame([$rexel->id], $item->suppliers()->where('is_preferred', true)->pluck('distributor_id')->all());
        $this->assertSame('6.9000', $item->fresh()->market_price);
        $this->assertSame('RX-16', $item->fresh()->distributor_reference);

        // The item page lists the suppliers with their prices; the edit page has them in the form.
        $this->get(StockItemResource::getUrl('view', ['record' => $item], panel: 'admin'))->assertOk()->assertSee('Elektro Peeters')->assertSee('P-16')->assertSee('7,25');
        $this->get(StockItemResource::getUrl('edit', ['record' => $item], panel: 'admin'))->assertOk()->assertSee('RX-16');

        // A newer fetched price keeps its source.
        $this->travel(1)->minutes();
        $item->suppliers()->where('distributor_id', $rexel->id)->sole()->update(['price' => 6.5, 'price_source' => 'rexel', 'price_updated_at' => now()]);
        $this->assertSame('rexel', $item->suppliers()->where('distributor_id', $rexel->id)->sole()->price_source);
    }

    #[Test]
    public function the_stock_list_shows_the_quantity_per_location_and_finds_supplier_references(): void
    {
        $luik = Location::factory()->create(['name' => 'Depot Luik', 'code' => 'LUI']);
        $gent = Location::factory()->create(['name' => 'Depot Gent', 'code' => 'GEN']);
        $item = StockItem::factory()->create(['name' => 'Kabel XVB']);
        $other = StockItem::factory()->create(['name' => 'Zekering']);
        $item->suppliers()->create(['distributor_id' => Distributor::factory()->create()->id, 'reference' => 'ZZ-998877']);
        app(AdjustStock::class)->handle($item->levels()->where('location_id', $luik->id)->sole(), 12, auth()->user());
        app(AdjustStock::class)->handle($item->levels()->where('location_id', $gent->id)->sole(), 3, auth()->user());

        Livewire::test(ListStockItems::class)
            ->assertTableColumnExists("location_{$luik->id}")
            ->assertTableColumnExists("location_{$gent->id}")
            ->assertTableColumnStateSet("location_{$luik->id}", '12.000', $item)
            ->assertTableColumnStateSet("location_{$gent->id}", '3.000', $item)
            ->searchTable('ZZ-998877')
            ->assertCanSeeTableRecords([$item])
            ->assertCanNotSeeTableRecords([$other]);
    }

    #[Test]
    public function only_those_with_the_permission_export_the_list_with_the_prices(): void
    {
        $location = Location::factory()->create(['code' => 'LUI']);
        $rexel = $this->rexel();
        $local = Distributor::factory()->create(['name' => 'Elektro Peeters']);
        $item = StockItem::factory()->create(['name' => 'Automaat 16A']);
        $item->suppliers()->create(['distributor_id' => $local->id, 'reference' => 'P-16', 'price' => 7.25, 'is_preferred' => true]);
        $item->suppliers()->create(['distributor_id' => $rexel->id, 'reference' => 'RX-16', 'price' => 6.9, 'price_source' => 'rexel', 'price_updated_at' => now()]);
        app(AdjustStock::class)->handle($item->levels()->sole(), 5, auth()->user());

        $rows = $this->read(app(StockExport::class)->xlsx(StockItem::query()->get()));
        $header = $rows[0];
        $row = $rows[1];
        $this->assertSame('Automaat 16A', $row[0]);
        $this->assertSame(5.0, (float) $row[array_search('LUI', $header, true)]);
        $this->assertSame(7.25, (float) $row[array_search(__('erp.fields.market_price'), $header, true)]);
        $this->assertSame('RX-16', $row[array_search(__('erp.stock.export.reference', ['name' => 'Rexel']), $header, true)]);
        $this->assertSame(6.9, (float) $row[array_search(__('erp.stock.export.price', ['name' => 'Rexel']), $header, true)]);
        $this->assertSame(7.25, (float) $row[array_search(__('erp.stock.export.price', ['name' => 'Elektro Peeters']), $header, true)]);

        // Administrators have it; employees only with "stock_prices.export".
        Livewire::test(ListStockItems::class)->assertActionVisible('export')->callAction('export')->assertFileDownloaded();

        $this->actingAs(User::factory()->withPermissions(['stock_items' => ['view'], 'stock_prices' => ['view']])->inTeam()->create());
        Filament::setCurrentPanel('app');
        Livewire::test(ListStockItems::class)->assertActionHidden('export');

        $this->actingAs(User::factory()->withPermissions(['stock_items' => ['view'], 'stock_prices' => ['view', 'export']])->inTeam()->create());
        Livewire::test(ListStockItems::class)->assertActionVisible('export')->callAction('export')->assertFileDownloaded();
    }
}
