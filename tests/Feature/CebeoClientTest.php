<?php

namespace Tests\Feature;

use App\Actions\RefreshStockItemFromDistributor;
use App\Models\Distributor;
use App\Models\Manufacturer;
use App\Models\StockItem;
use App\Services\Distributors\CebeoClient;
use App\Services\Distributors\DistributorException;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CebeoClientTest extends TestCase
{
    use RefreshDatabase;

    protected function cebeo(): Distributor
    {
        return Distributor::factory()->create(['name' => 'Cebeo', 'price_provider' => 'cebeo', 'api_username' => 'IT TEST', 'api_customer_number' => '12345', 'api_password' => 'pw&<']);
    }

    /**
     * An answer as Cebeo sends it (cebeoXML 2.0.0), with the given message and items.
     */
    protected static function answer(int $code, string $message, string $items = ''): string
    {
        $list = $items === '' ? '' : "<Article><List><NumberOfLines>1</NumberOfLines>{$items}</List></Article>";

        return '<?xml version="1.0" encoding="UTF-8"?><cebeoXML version="2.0.0"><Response><Message code="'.$code.'">'.$message.'</Message>'.$list.'</Response></cebeoXML>';
    }

    protected static function item(string $code, string $reference, string $price, int $stock = 9221): string
    {
        return "<Item><Material><SupplierItemID>{$code}</SupplierItemID><CustomerItemID/><BrandCode>36</BrandCode><Reference>{$reference}</Reference><ReelCode/><ReelLength>0</ReelLength><PromotionCode/><BrandName>NIKO</BrandName><Description>3623-366 Niko wandcontactdoos</Description></Material><StockCode>A</StockCode><Stock>{$stock}</Stock><UnitOfMeasure>PCE</UnitOfMeasure><UnitPrice><NetPrice>{$price}</NetPrice><TarifPrice>7,1000</TarifPrice><Ecotax>0,0000</Ecotax><Recupel>0,0000</Recupel><Sabam>0,0000</Sabam><TAV>21,00</TAV></UnitPrice><SalesPackQuantity>10</SalesPackQuantity></Item>";
    }

    #[Test]
    public function the_net_price_comes_from_cebeos_xml_web_service(): void
    {
        Http::fake(['b2b.cebeo.be/*' => Http::response(self::answer(0, 'XML message received and validated all right', self::item('171418', '23-366', '6,8500')))]);
        $item = StockItem::factory()->for($this->cebeo())->create(['distributor_reference' => '171418', 'manufacturer_reference' => null]);

        app(RefreshStockItemFromDistributor::class)->handle($item);

        $this->assertSame('6.8500', $item->fresh()->market_price);
        $this->assertSame('cebeo', $item->fresh()->price_source);
        Http::assertSent(fn (Request $request): bool => $request->url() === CebeoClient::ENDPOINT
            && str_contains($request->body(), 'version="2.0.0"')
            && str_contains($request->body(), '<Customer><CustomerNumber>12345</CustomerNumber><UserName>IT TEST</UserName><Password>pw&amp;&lt;</Password></Customer>')
            && str_contains($request->body(), '<Article><Get><Material><SupplierItemID>171418</SupplierItemID></Material></Get></Article>'));
    }

    #[Test]
    public function without_a_cebeo_number_the_manufacturer_reference_is_searched(): void
    {
        Http::fake(['b2b.cebeo.be/*' => fn (Request $request) => Http::response(str_contains($request->body(), '<Search>')
            ? self::answer(0, 'ok', self::item('9999', '23-366-01', '1,0000').self::item('171418', '23-366', '6,8500'))
            : self::answer(1, 'ART0011'))]);
        $cebeo = $this->cebeo();

        // The article number is unknown (ART0011): the search picks the matching reference.
        $info = $cebeo->client()->lookup(StockItem::factory()->for($cebeo)->make(['distributor_reference' => '000', 'manufacturer_reference' => '23 366']));
        $this->assertSame(6.85, $info->price);
        $this->assertSame('3623-366 Niko wandcontactdoos', $info->name);
    }

    #[Test]
    public function errors_from_cebeo_are_reported(): void
    {
        $cebeo = $this->cebeo();
        $item = StockItem::factory()->for($cebeo)->make(['distributor_reference' => '171418']);

        Http::fake(['b2b.cebeo.be/*' => Http::response(self::answer(13, 'Invalid XML element data: CustomerNumber (Data: )'))]);

        try {
            $cebeo->client()->lookup($item);
            $this->fail('Expected Cebeo to refuse the request.');
        } catch (DistributorException $exception) {
            $this->assertStringContainsString('13: Invalid XML element data', $exception->getMessage());
        }

        // Without customer number, user and password it is not set up.
        $this->assertFalse(Distributor::factory()->create(['price_provider' => 'cebeo', 'api_username' => 'X', 'api_password' => 'Y'])->client()->isConfigured());
    }

    #[Test]
    public function no_longer_sold_articles_keep_their_price_and_the_best_match_wins(): void
    {
        $cebeo = $this->cebeo();

        // Price 0 (no longer sold): no price, so the item keeps the one it has.
        Http::fake(['b2b.cebeo.be/*' => Http::response(self::answer(0, 'ok', self::item('171418', '23-366', '0,0000', 0)))]);
        $item = StockItem::factory()->for($cebeo)->create(['distributor_reference' => '171418']);
        $item->suppliers()->sole()->update(['price' => 6.5]);
        app(RefreshStockItemFromDistributor::class)->handle($item);
        $this->assertSame('6.5000', $item->fresh()->market_price);

        // Several articles share the reference: the one with a price and the most stock.
        Http::swap(new Factory);
        Http::fake(['b2b.cebeo.be/*' => Http::response(self::answer(0, 'ok', self::item('1', 'XVB3G2,5N-CCA', '0,0000', 0).self::item('2', 'XVB3G2,5N-CCA', '1,8073', 100).self::item('3', 'XVB3G2,5N-CCA', '1,7571', 71800)))]);
        $this->assertSame(1.7571, $cebeo->client()->lookup(StockItem::factory()->for($cebeo)->make(['distributor_reference' => null, 'manufacturer_reference' => 'XVB3G2,5N-CCA']))->price);
    }

    #[Test]
    public function prices_come_from_the_manufacturers_distributors_by_manufacturer_reference(): void
    {
        Http::preventStrayRequests();
        Http::fake(['b2b.cebeo.be/*' => fn (Request $request) => Http::response(str_contains($request->body(), '<Search>')
            ? self::answer(0, 'ok', self::item('171418', '23-366', '6,8500'))
            : self::answer(1, 'ART0011'))]);
        $cebeo = $this->cebeo();
        $rexel = Distributor::factory()->create(['name' => 'Rexel', 'price_provider' => 'rexel', 'api_username' => 'X', 'api_customer_number' => '1', 'api_password' => 'p']);
        $manufacturer = Manufacturer::factory()->create();
        $manufacturer->distributors()->attach($cebeo);

        // Rexel is a supplier of the item, but not a distributor of the manufacturer: it is not asked.
        $item = StockItem::factory()->for($manufacturer)->for($rexel)->create(['manufacturer_reference' => '23 366', 'distributor_reference' => 'R1']);
        $this->assertTrue(app(RefreshStockItemFromDistributor::class)->canFetch($item));

        app(RefreshStockItemFromDistributor::class)->handle($item);

        // Cebeo is added as supplier; its article number comes from the first answer.
        $supplier = $item->suppliers()->where('distributor_id', $cebeo->id)->firstOrFail();
        $this->assertSame('171418', $supplier->reference);
        $this->assertSame('6.8500', $supplier->price);
        $this->assertSame('cebeo', $supplier->price_source);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '<Keyword>23 366</Keyword>'));

        // An article number filled in by hand is kept.
        $supplier->update(['reference' => '555']);
        app(RefreshStockItemFromDistributor::class)->handle($item->fresh());
        $this->assertSame('555', $supplier->fresh()->reference);
    }

    #[Test]
    public function a_new_item_fetches_its_price_and_every_item_is_updated_at_night(): void
    {
        config(['services.distributors.fetch_price_on_create' => true]);
        app(Dispatcher::class)->withoutDispatchingAfterResponses();
        Http::preventStrayRequests();
        Http::fake(['b2b.cebeo.be/*' => Http::sequence()
            ->push(self::answer(0, 'ok', self::item('171418', '23-366', '6,8500')))
            ->push(self::answer(0, 'ok', self::item('171418', '23-366', '7,1000')))]);
        $manufacturer = Manufacturer::factory()->create();
        $manufacturer->distributors()->attach($this->cebeo());

        $item = StockItem::factory()->for($manufacturer)->create(['manufacturer_reference' => '23-366', 'distributor_reference' => null]);

        $this->assertSame('6.8500', $item->fresh()->market_price);
        $this->assertSame('171418', $item->fresh()->distributor_reference);

        $this->artisan('stock:update-prices', ['--sleep' => 0])->assertSuccessful();

        $this->assertSame('7.1000', $item->fresh()->market_price);
        $this->assertCount(2, $item->prices);
    }
}
