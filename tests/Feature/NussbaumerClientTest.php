<?php

namespace Tests\Feature;

use App\Actions\RefreshStockItemFromDistributor;
use App\Models\Distributor;
use App\Models\Manufacturer;
use App\Models\StockItem;
use App\Services\Distributors\DistributorException;
use App\Services\Distributors\NussbaumerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nussbaumer through the Dynamicweb interface of its webshop: no real account is used, the answers are
 * shaped like the shop's (product search of the "Products" index).
 */
class NussbaumerClientTest extends TestCase
{
    use RefreshDatabase;

    protected function nussbaumer(): Distributor
    {
        return Distributor::factory()->create(['name' => 'Nussbaumer', 'price_provider' => 'nussbaumer', 'api_username' => 'erp@power.example', 'api_password' => 'geheim']);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function product(string $number, string $artRef, float $price): array
    {
        return [
            'id' => 'A015758', 'number' => $number, 'name' => 'Kabelschoen Al/Cu rm150²-re185² M10', 'shortDescription' => '',
            'price' => ['price' => $price, 'priceWithoutVat' => $price, 'priceWithVat' => round($price * 1.21, 2)],
            'defaultImage' => ['value' => '/Files/Files/Integration/product_images/A015758_P1.jpg'],
            'productFields' => ['Art_ref' => ['systemName' => 'Art_ref', 'value' => $artRef], 'Merk' => ['value' => []]],
        ];
    }

    protected function fake(array $products, int $loginStatus = 200): void
    {
        Http::fake([
            NussbaumerClient::BASE.'/dwapi/users/authenticate' => Http::response($loginStatus === 200 ? ['token' => 'jwt-token'] : ['title' => 'Unauthorized'], $loginStatus),
            NussbaumerClient::BASE.'/dwapi/ecommerce/products/search*' => Http::response(['products' => $products, 'totalProductsCount' => count($products)]),
        ]);
    }

    #[Test]
    public function the_price_is_read_by_manufacturer_reference_with_the_webshop_login(): void
    {
        $nussbaumer = $this->nussbaumer();
        // The search also returns a near match: only the product whose Art. Ref. is the reference is taken.
        $this->fake([self::product('A015758', '363R/10', 9.99), self::product('A055585', '370R/10', 4.37)]);
        $manufacturer = Manufacturer::factory()->create();
        $manufacturer->distributors()->attach($nussbaumer);
        $item = StockItem::factory()->for($manufacturer)->create(['manufacturer_reference' => '370R/10', 'distributor_reference' => null, 'image' => 'x.png']);

        app(RefreshStockItemFromDistributor::class)->handle($item);

        $supplier = $item->suppliers()->where('distributor_id', $nussbaumer->id)->sole();
        $this->assertSame(['A055585', '4.3700', 'nussbaumer'], [$supplier->reference, $supplier->price, $supplier->price_source]);
        Http::assertSent(fn (Request $request): bool => $request->url() === NussbaumerClient::BASE.'/dwapi/users/authenticate' && $request['username'] === 'erp@power.example' && $request['password'] === 'geheim');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/dwapi/ecommerce/products/search') && str_contains($request->url(), 'q=370R%2F10')
            && str_contains($request->url(), 'RepositoryName=Products') && $request->hasHeader('Authorization', 'Bearer jwt-token'));
    }

    #[Test]
    public function nussbaumers_own_number_works_too_and_another_manufacturers_product_is_not_taken(): void
    {
        $client = $this->nussbaumer()->client();
        $this->fake([self::product('A055585', '370R/10', 4.37)]);

        $info = $client->lookup(StockItem::factory()->make(['manufacturer_reference' => null, 'distributor_reference' => 'a055585']));
        $this->assertSame([4.37, 'A055585', NussbaumerClient::BASE.'/Files/Files/Integration/product_images/A015758_P1.jpg'], [$info->price, $info->reference, $info->imageUrl]);

        // A different Art. Ref.: not this article.
        $this->assertNull($client->lookup(StockItem::factory()->make(['manufacturer_reference' => '999X', 'distributor_reference' => null])));

        // Without a price in the shop (not signed in, or price on request): no price instead of 0.
        $this->assertNull(NussbaumerClient::productInfo(self::product('A1', 'X', 0))->price);
    }

    #[Test]
    public function a_refused_login_says_so_and_without_login_it_is_not_set_up(): void
    {
        $this->fake([], loginStatus: 401);

        try {
            $this->nussbaumer()->client()->lookup(StockItem::factory()->make(['manufacturer_reference' => '370R/10']));
            $this->fail('The refused login must be reported.');
        } catch (DistributorException $exception) {
            $this->assertStringContainsString('Nussbaumer', $exception->getMessage());
        }

        $this->assertFalse(Distributor::factory()->make(['price_provider' => 'nussbaumer', 'api_username' => null])->client()->isConfigured());
    }
}
