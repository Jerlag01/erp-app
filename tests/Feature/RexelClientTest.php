<?php

namespace Tests\Feature;

use App\Actions\RefreshStockItemFromDistributor;
use App\Models\Distributor;
use App\Models\StockItem;
use App\Services\Distributors\DistributorException;
use App\Services\Distributors\RexelClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RexelClientTest extends TestCase
{
    use RefreshDatabase;

    protected function rexel(): Distributor
    {
        return Distributor::factory()->create(['name' => 'Rexel', 'price_provider' => 'rexel', 'api_username' => 'TESTWEBS', 'api_customer_number' => '123456', 'api_password' => 'geheim&<x']);
    }

    /**
     * A SOAP answer as Rexel sends it: the XML (status and content) as an escaped string in <return>.
     */
    protected static function soap(string $method, int $status, string $content = ''): string
    {
        $inner = "<?xml version='1.0' encoding='utf-8'?><Root><Status><StatusCode>{$status}</StatusCode><StatusDescription>".($status === 0 ? 'Transfer successful' : 'Invalid password')."</StatusDescription></Status><Content>{$content}</Content></Root>";

        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns2:'.$method.'Response xmlns:ns2="rexel.ws.service"><return>'.htmlspecialchars($inner, ENT_XML1).'</return></ns2:'.$method.'Response></soap:Body></soap:Envelope>';
    }

    protected static function price(string $code, string $net): string
    {
        return $code === ''
            ? '<PriceDetail><ItemCode></ItemCode><NetPrice>0.0000</NetPrice><Available>0.000</Available><LeadTime>0</LeadTime></PriceDetail>'
            : "<PriceDetail><ItemCode>{$code}</ItemCode><SupplierItemCode>ABC</SupplierItemCode><EanCode>5412748101023</EanCode><ItemDescription>Kabel XVB 3G2,5</ItemDescription><SalesUnitCode>M</SalesUnitCode><BasePrice>2.1000</BasePrice><NetPrice>{$net}</NetPrice><TotalSurcharge>0.0500</TotalSurcharge><Ecotax>0.0000</Ecotax><Recupel>0.0500</Recupel><Sabam>0.0000</Sabam><VATCode>21.00</VATCode><CurrencyCode>EUR</CurrencyCode><Available>250.000</Available><LeadTime>1</LeadTime></PriceDetail>";
    }

    #[Test]
    public function the_net_price_and_image_come_from_rexels_web_services(): void
    {
        Storage::fake('public');
        Http::fake([
            'services.rexel.be/*' => fn (Request $request) => Http::response(str_contains($request->body(), 'getPrices')
                ? self::soap('getPrices', 0, self::price('2185540', '1.2345'))
                : self::soap('getArticleInformation', 0, '<ArticleDetail><IsActive>true</IsActive><ItemCode>2185540</ItemCode><ItemDescription>Kabel XVB 3G2,5</ItemDescription><LongItemDescription>Kabel XVB-F2 3G2,5 mm² per meter</LongItemDescription><BrandDescription>Nexans</BrandDescription><ImageLinkHigh>https://example.com/2185540.png</ImageLinkHigh><ImageLinkLow></ImageLinkLow></ArticleDetail>')),
            'example.com/2185540.png' => Http::response('fake-png', 200, ['Content-Type' => 'image/png']),
        ]);
        $item = StockItem::factory()->for($this->rexel())->create(['distributor_reference' => '2185540', 'image' => null]);

        app(RefreshStockItemFromDistributor::class)->handle($item);

        $this->assertSame('1.2345', $item->fresh()->market_price);
        $this->assertSame('rexel', $item->fresh()->price_source);
        $this->assertNotNull($item->fresh()->image);

        // The SOAP call as the WSDL describes it, with the credentials of the distributor (escaped).
        Http::assertSent(fn (Request $request): bool => $request->url() === RexelClient::ENDPOINT
            && str_contains($request->body(), '<ser:getPrices>')
            && str_contains($request->body(), '<customer>123456</customer><user>TESTWEBS</user><password>geheim&amp;&lt;x</password>')
            && str_contains($request->body(), '<itemData><itemCode>2185540</itemCode><quantity>1</quantity></itemData>'));
    }

    #[Test]
    public function an_unknown_code_is_not_found_and_falls_back_to_the_manufacturer_reference(): void
    {
        Http::fake([
            'services.rexel.be/*' => fn (Request $request) => Http::response(match (true) {
                str_contains($request->body(), 'getArticleInformation') => self::soap('getArticleInformation', 0, '<ArticleDetail><IsActive>true</IsActive><ItemCode>E123456</ItemCode></ArticleDetail>'),
                str_contains($request->body(), '<itemCode>E123456</itemCode>') => self::soap('getPrices', 0, self::price('E123456', '9.9900')),
                default => self::soap('getPrices', 0, self::price('', '')),
            }),
        ]);
        $rexel = $this->rexel();

        $this->assertNull($rexel->client()->lookup(StockItem::factory()->for($rexel)->make(['distributor_reference' => '0000000', 'manufacturer_reference' => null])));
        $this->assertSame(9.99, $rexel->client()->lookup(StockItem::factory()->for($rexel)->make(['distributor_reference' => '0000000', 'manufacturer_reference' => 'E123456']))->price);
    }

    #[Test]
    public function a_refused_login_or_an_unreachable_service_is_reported(): void
    {
        $rexel = $this->rexel();
        $item = StockItem::factory()->for($rexel)->make(['distributor_reference' => '2185540']);

        Http::fake(['services.rexel.be/*' => Http::response(self::soap('getPrices', 12))]);

        try {
            $rexel->client()->lookup($item);
            $this->fail('Expected the login to be refused.');
        } catch (DistributorException $exception) {
            $this->assertStringContainsString('12: Invalid password', $exception->getMessage());
        }

        Http::swap(new Factory);
        Http::fake(['services.rexel.be/*' => Http::response('<html>Bad gateway</html>', 502)]);
        $this->expectException(DistributorException::class);
        $rexel->client()->lookup($item);
    }

    #[Test]
    public function without_customer_number_user_and_password_it_is_not_configured(): void
    {
        $rexel = Distributor::factory()->create(['price_provider' => 'rexel', 'api_username' => 'TESTWEBS']);

        $this->assertFalse($rexel->client()->isConfigured());
        $this->assertTrue($this->rexel()->client()->isConfigured());
    }
}
