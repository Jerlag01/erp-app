<?php

namespace Tests\Feature;

use App\Actions\RefreshStockItemFromDistributor;
use App\Models\Distributor;
use App\Models\StockItem;
use App\Services\Distributors\CebeoClient;
use App\Services\Distributors\DistributorException;
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
        $item = StockItem::factory()->for($this->cebeo())->create(['distributor_reference' => '171418']);

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
}
