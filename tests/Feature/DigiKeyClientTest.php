<?php

namespace Tests\Feature;

use App\Actions\RefreshStockItemFromDistributor;
use App\Filament\Resources\Distributors\Pages\EditDistributor;
use App\Models\Distributor;
use App\Models\DistributorOrder;
use App\Models\Manufacturer;
use App\Models\StockItem;
use App\Models\User;
use App\Services\Distributors\DigiKeyClient;
use App\Services\Distributors\DistributorException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DigiKeyClientTest extends TestCase
{
    use RefreshDatabase;

    protected function digiKey(array $attributes = []): Distributor
    {
        return Distributor::factory()->create(['name' => 'DigiKey', 'price_provider' => 'digikey', 'api_username' => 'client-id', 'api_password' => 'client-secret', ...$attributes]);
    }

    protected static function token(): array
    {
        return ['api.digikey.com/v1/oauth2/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 599, 'token_type' => 'Bearer'])];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function product(): array
    {
        return ['Product' => [
            'Description' => ['ProductDescription' => 'CAP ALUM 1000UF 20% 35V RADIAL', 'DetailedDescription' => '1000 µF 35 V Aluminum Electrolytic Capacitors Radial'],
            'Manufacturer' => ['Id' => 10, 'Name' => 'Panasonic Industry'],
            'ManufacturerProductNumber' => 'ECA-1VHG102',
            'UnitPrice' => 1.25,
            'PhotoUrl' => 'https://mm.digikey.com/photo.jpg',
            'ProductVariations' => [
                ['DigiKeyProductNumber' => 'P5555TR-ND', 'PackageType' => ['Id' => 1, 'Name' => 'Tape & Reel (TR)'], 'MinimumOrderQuantity' => 500, 'MarketPlace' => false, 'StandardPricing' => [['BreakQuantity' => 500, 'UnitPrice' => 0.5, 'TotalPrice' => 250]], 'MyPricing' => []],
                ['DigiKeyProductNumber' => 'P5555-ND', 'PackageType' => ['Id' => 3, 'Name' => 'Bulk'], 'MinimumOrderQuantity' => 1, 'MarketPlace' => false, 'StandardPricing' => [['BreakQuantity' => 10, 'UnitPrice' => 0.806, 'TotalPrice' => 8.06], ['BreakQuantity' => 1, 'UnitPrice' => 1.25, 'TotalPrice' => 1.25]], 'MyPricing' => [['BreakQuantity' => 1, 'UnitPrice' => 1.1, 'TotalPrice' => 1.1]]],
            ],
        ]];
    }

    /**
     * A sales order as Order Status V4 returns it, with one line of which $shipped of 10 are shipped.
     *
     * @return array<string, mixed>
     */
    protected static function salesOrder(int $shipped, string $status = 'Processing'): array
    {
        return [
            'SalesOrderId' => 81234567, 'OrderNumber' => 71000001, 'CustomerId' => 1234, 'DateEntered' => '2026-11-03T09:12:00Z',
            'PurchaseOrder' => '466/1126/JL', 'TotalPrice' => 12.5, 'Currency' => 'EUR',
            'Status' => ['SalesOrderStatus' => $status, 'ShortDescription' => $status === 'Delivered' ? 'Delivered' : 'Processing'],
            'Contact' => ['FirstName' => 'Jan', 'LastName' => 'Peeters'],
            'LineItems' => [[
                'DigiKeyProductNumber' => 'P5555-ND', 'ManufacturerProductNumber' => 'ECA-1VHG102', 'Description' => 'CAP ALUM 1000UF 20% 35V RADIAL',
                'QuantityOrdered' => 10, 'QuantityShipped' => $shipped, 'UnitPrice' => 1.25, 'PackType' => 'Bulk',
                'ItemShipments' => $shipped > 0 ? [['QuantityShipped' => $shipped, 'ShippedDate' => '2026-11-05T00:00:00Z', 'TrackingNumber' => '1Z999', 'ExpectedDeliveryDate' => '2026-11-07T00:00:00Z']] : [],
                'Schedules' => $shipped < 10 ? [['QuantityScheduled' => 10 - $shipped, 'ScheduledDate' => '2026-11-20T00:00:00Z']] : [],
            ]],
        ];
    }

    #[Test]
    public function the_price_and_digikey_number_come_from_product_information_by_manufacturer_reference(): void
    {
        Http::preventStrayRequests();
        Http::fake([...self::token(), 'api.digikey.com/products/v4/search/*' => Http::response(self::product())]);
        $digiKey = $this->digiKey();
        $manufacturer = Manufacturer::factory()->create();
        $manufacturer->distributors()->attach($digiKey);
        $item = StockItem::factory()->for($manufacturer)->create(['manufacturer_reference' => 'ECA-1VHG102', 'distributor_reference' => null, 'image' => 'x.png']);

        app(RefreshStockItemFromDistributor::class)->handle($item);

        // The account's own price for one piece of the package type sold per piece (not the reel).
        $supplier = $item->suppliers()->where('distributor_id', $digiKey->id)->sole();
        $this->assertSame(['P5555-ND', '1.1000', 'digikey'], [$supplier->reference, $supplier->price, $supplier->price_source]);
        Http::assertSent(fn (Request $request): bool => $request->url() === DigiKeyClient::TOKEN_URL && $request['grant_type'] === 'client_credentials' && $request['client_secret'] === 'client-secret');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/products/v4/search/ECA-1VHG102/productdetails')
            && $request->hasHeader('Authorization', 'Bearer app-token') && $request->hasHeader('X-DIGIKEY-Client-Id', 'client-id')
            && $request->hasHeader('X-DIGIKEY-Locale-Site', 'BE') && $request->hasHeader('X-DIGIKEY-Locale-Currency', 'EUR'));

        // Another manufacturer's product with that number is not taken.
        $this->assertNull($digiKey->client()->lookup(StockItem::factory()->make(['manufacturer_reference' => 'OTHER-1', 'distributor_reference' => null])));
    }

    #[Test]
    public function an_unknown_product_is_not_found(): void
    {
        Http::fake([...self::token(), 'api.digikey.com/products/*' => Http::response(['title' => 'Not Found', 'status' => 404, 'detail' => 'Requested Product x Not Found'], 404)]);
        $this->assertNull($this->digiKey()->client()->lookup(StockItem::factory()->make(['manufacturer_reference' => 'X', 'distributor_reference' => null])));
    }

    #[Test]
    public function a_refused_login_is_reported(): void
    {
        Http::fake(['api.digikey.com/v1/oauth2/token' => Http::response(['error' => 'invalid_client', 'error_description' => 'Client authentication failed'], 401)]);
        $this->expectException(DistributorException::class);
        $this->expectExceptionMessage('Client authentication failed');
        $this->digiKey(['name' => 'DigiKey 2'])->client()->lookup(StockItem::factory()->make(['manufacturer_reference' => 'X']));
    }

    #[Test]
    public function the_orders_of_the_account_are_synced_and_followed_up(): void
    {
        $shipped = 4;
        $status = 'ProcessingPartialShipment';
        Http::preventStrayRequests();
        Http::fake([
            ...self::token(),
            'api.digikey.com/orderstatus/v4/orders*' => function () use (&$shipped, &$status) {
                return Http::response(['TotalOrders' => 1, 'Orders' => [[
                    'OrderNumber' => 71000001, 'DateEntered' => '2026-11-03T09:12:00Z', 'PONumber' => '466/1126/JL',
                    'EntireOrderStatus' => ['OrderStatus' => $status, 'ShortDescription' => 'Partially shipped'],
                    'SalesOrders' => [self::salesOrder($shipped, $status)],
                ]]]);
            },
            'api.digikey.com/orderstatus/v4/salesorder/81234567' => function () use (&$shipped, &$status) {
                return Http::response(self::salesOrder($shipped, $status));
            },
            'api.digikey.com/orderstatus/v4/salesorder/*' => Http::response(['title' => 'Not Found', 'status' => 404], 404),
        ]);

        // Without the Account ID there are no orders to fetch.
        $digiKey = $this->digiKey();
        $this->assertNull($digiKey->client()->supportsOrders() ? 'yes' : null);

        $digiKey->update(['api_customer_number' => '7654321']);
        $this->artisan('distributors:sync-orders')->assertSuccessful();

        $order = DistributorOrder::query()->sole();
        $this->assertSame(['81234567', '466/1126/JL', DistributorOrder::PARTIAL, '2026-11-03', '2026-11-20'], [$order->external_number, $order->customer_reference, $order->status, $order->ordered_at->toDateString(), $order->expected_delivery->toDateString()]);
        $this->assertSame(['71000001', 'Jan Peeters', ['1Z999']], [$order->raw['order_number'], $order->raw['ordered_by'], $order->raw['tracking']]);
        $this->assertSame([['P5555-ND', 'ECA-1VHG102', 10.0, 4.0]], $order->lines()->get()->map(fn ($line): array => [$line->distributor_reference, $line->manufacturer_reference, (float) $line->quantity, (float) $line->quantity_delivered])->all());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/orderstatus/v4/orders?') && $request->hasHeader('X-DIGIKEY-Account-Id', '7654321') && str_contains($request->url(), 'Shared=true'));

        // Every hour: the rest was shipped and delivered.
        $shipped = 10;
        $status = 'Delivered';
        $this->artisan('distributors:sync-orders')->assertSuccessful();
        $order->refresh();
        $this->assertSame([DistributorOrder::DELIVERED, '2026-11-05'], [$order->status, $order->delivered_at->toDateString()]);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/orderstatus/v4/salesorder/81234567'));

        $this->assertNull($digiKey->fresh()->client()->order('999'));
    }

    #[Test]
    public function a_digikey_label_is_scanned_in_the_app(): void
    {
        $user = User::factory()->withPermissions(['stock_items' => ['view']])->inTeam()->create();
        $auth = $this->mobileAuth($user);
        $digiKey = $this->digiKey(['api_refresh_token' => 'refresh-1', 'api_refresh_expires_at' => now()->addDays(80)]);
        $item = StockItem::factory()->create(['manufacturer_reference' => 'ECA-1VHG102', 'distributor_reference' => null]);
        $other = StockItem::factory()->create(['manufacturer_reference' => 'GRM188R71H104KA93D', 'distributor_reference' => null]);
        $item->suppliers()->create(['distributor_id' => $digiKey->id, 'reference' => 'P5555-ND']);
        Http::preventStrayRequests();
        Http::fake([
            'api.digikey.com/v1/oauth2/token' => Http::response(['access_token' => 'user-token', 'refresh_token' => 'refresh-2', 'expires_in' => 599, 'refresh_token_expires_in' => 7776000]),
            'api.digikey.com/Barcoding/v3/ProductBarcodes/*' => Http::response(['DigiKeyPartNumber' => '490-1512-1-ND', 'ManufacturerPartNumber' => 'GRM 188R71H104KA93D', 'ManufacturerName' => 'Murata', 'Quantity' => 100]),
        ]);

        // The 2D label holds the numbers: read without a call.
        $label = "[)>\x1e06\x1dPP5555-ND\x1d1PECA-1VHG102\x1d1K81234567\x1dQ10\x1e\x04";
        $this->getJson('/api/v1/stock/lookup?code='.rawurlencode($label), $auth)->assertOk()->assertJsonPath('data.id', $item->id);
        Http::assertNothingSent();

        // An older 1D label through the barcode API with the user login (the refresh token is renewed).
        $this->getJson('/api/v1/stock/lookup?code=0490151210000100', $auth)->assertOk()->assertJsonPath('data.id', $other->id);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/Barcoding/v3/ProductBarcodes/0490151210000100') && $request->hasHeader('Authorization', 'Bearer user-token'));
        $this->assertSame('refresh-2', $digiKey->fresh()->api_refresh_token);
    }

    #[Test]
    public function an_administrator_connects_a_digikey_user_login(): void
    {
        $admin = User::factory()->admin()->create();
        $digiKey = $this->digiKey();
        Http::preventStrayRequests();
        Http::fake([
            'api.digikey.com/v1/oauth2/token' => Http::response(['access_token' => 'user-token', 'refresh_token' => 'refresh-1', 'expires_in' => 599, 'refresh_token_expires_in' => 7776000]),
            'api.digikey.com/CustomerResource/v1/associatedaccounts' => Http::response(['Accounts' => [['AccountId' => 7654321, 'Address' => ['CompanyName' => 'Power Installation']]]]),
        ]);

        $this->actingAs(User::factory()->create())->get(route('distributors.digikey.connect', $digiKey))->assertForbidden();

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Livewire::test(EditDistributor::class, ['record' => $digiKey->getRouteKey()])
            ->assertSuccessful()
            ->assertSee(route('distributors.digikey.callback'))
            ->assertActionVisible('connectDigiKey')
            ->assertActionHidden('disconnectDigiKey');

        $redirect = $this->actingAs($admin)->get(route('distributors.digikey.connect', $digiKey))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith(DigiKeyClient::AUTHORIZE_URL.'?response_type=code&client_id=client-id', $redirect);
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);
        $this->assertSame(route('distributors.digikey.callback'), $query['redirect_uri']);

        // A wrong state is refused.
        $this->get(route('distributors.digikey.callback', ['code' => 'abc', 'state' => 'wrong']))->assertForbidden();

        $this->get(route('distributors.digikey.connect', $digiKey));
        $state = session('digikey_oauth.state');
        $this->get(route('distributors.digikey.callback', ['code' => 'abc', 'state' => $state]))->assertRedirect();

        $digiKey->refresh();
        $this->assertSame(['refresh-1', '7654321'], [$digiKey->api_refresh_token, $digiKey->api_customer_number]);
        $this->assertTrue($digiKey->client()->isConnected());
        Http::assertSent(fn (Request $request): bool => $request->url() === DigiKeyClient::TOKEN_URL && $request['grant_type'] === 'authorization_code' && $request['code'] === 'abc');
    }
}
