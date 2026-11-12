<?php

namespace Tests\Feature;

use App\Actions\Purchasing\SyncDistributorOrders;
use App\Filament\Resources\DistributorOrders\DistributorOrderResource;
use App\Filament\Resources\DistributorOrders\Pages\CreateDistributorOrder;
use App\Filament\Resources\DistributorOrders\Pages\ListDistributorOrders;
use App\Filament\Resources\StockCategories\Pages\ManageStockCategories;
use App\Models\Distributor;
use App\Models\DistributorOrder;
use App\Models\OrderReference;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\User;
use App\Services\Distributors\DistributorClients;
use App\Services\Distributors\DistributorException;
use App\Services\Distributors\OrderInfo;
use App\Services\Distributors\OrderLineInfo;
use App\Services\Distributors\WurthClient;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The orders at the distributors (Purchasing > Orders), Würth as a prepared connection, and the stock
 * categories translated automatically from Dutch.
 */
class DistributorOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    protected function reference(int $number = 466, int $month = 10, int $year = 2026, string $initials = 'JL'): OrderReference
    {
        return OrderReference::factory()->create(['number' => $number, 'month' => $month, 'year' => $year, 'initials' => $initials, 'reference' => OrderReference::format($number, $month, $year, $initials)]);
    }

    #[Test]
    public function our_order_reference_is_found_in_the_distributor_order_and_linked(): void
    {
        $this->assertSame(['466/1026/JL'], DistributorOrder::referencesIn('PO werf Gent 466 / 1026 / jl'));
        $this->assertSame(['007/1126/AB', '012/1126/JL'], DistributorOrder::referencesIn('7/1126/AB en 012/1126/JL'));
        $this->assertSame([], DistributorOrder::referencesIn('Order 4500012345 of 12/11/2026'));

        $reference = $this->reference();
        $distributor = Distributor::factory()->create(['name' => 'Rexel']);

        // Linked only when our reference is in the order.
        $linked = DistributorOrder::query()->create(['distributor_id' => $distributor->id, 'external_number' => 'R-1', 'customer_reference' => 'Werf Gent 466/1026/JL']);
        $this->assertTrue($linked->orderReference->is($reference));
        $other = DistributorOrder::query()->create(['distributor_id' => $distributor->id, 'external_number' => 'R-2', 'customer_reference' => 'Werf Gent']);
        $this->assertNull($other->order_reference_id);
        $unknown = DistributorOrder::query()->create(['distributor_id' => $distributor->id, 'external_number' => 'R-3', 'customer_reference' => '467/1026/JL']);
        $this->assertNull($unknown->order_reference_id, 'A reference that does not exist (yet) is not linked.');

        // Taken later: the order that mentions it gets linked then.
        $later = $this->reference(467);
        $this->assertTrue($unknown->fresh()->orderReference->is($later));

        // A link made by hand is never replaced.
        $other->update(['order_reference_id' => $later->id, 'customer_reference' => 'Werf Gent 466/1026/JL']);
        $this->assertTrue($other->fresh()->orderReference->is($later));
        $this->assertSame(2, $later->distributorOrders()->count());
    }

    #[Test]
    public function orders_from_an_api_are_stored_with_their_lines_and_matched_to_stock_items(): void
    {
        $this->reference(12, 11);
        $rexel = Distributor::factory()->create(['name' => 'Rexel', 'price_provider' => 'rexel']);
        $cable = StockItem::factory()->create(['distributor_id' => $rexel->id, 'distributor_reference' => 'XVB3G25', 'manufacturer_reference' => 'NKT-3G2.5']);

        $order = app(SyncDistributorOrders::class)->store($rexel, new OrderInfo(
            number: '4500012345',
            customerReference: '012/1126/JL',
            orderedAt: Carbon::parse('2026-11-10'),
            status: DistributorOrder::PARTIAL,
            expectedDelivery: Carbon::parse('2026-11-14'),
            lines: [
                new OrderLineInfo(distributorReference: 'XVB3G25', description: 'XVB 3G2,5', quantity: 100, quantityDelivered: 100, unit: 'm'),
                new OrderLineInfo(manufacturerReference: 'ABB-123', description: 'Automaat 16A', quantity: 4, quantityDelivered: 0),
            ],
        ));

        $this->assertSame(['012/1126/JL', DistributorOrder::SOURCE_API, DistributorOrder::PARTIAL], [$order->orderReference->reference, $order->source, $order->status]);
        $this->assertTrue($order->lines[0]->stockItem->is($cable));
        $this->assertNull($order->lines[1]->stock_item_id);
        $this->assertSame(__('erp.distributor_orders.progress', ['done' => 1, 'total' => 2]), $order->deliveryProgress());

        // Fetched again: the same order is updated, not added twice.
        app(SyncDistributorOrders::class)->store($rexel, new OrderInfo(number: '4500012345', customerReference: '012/1126/JL', status: DistributorOrder::DELIVERED, lines: [new OrderLineInfo(distributorReference: 'XVB3G25', quantity: 100, quantityDelivered: 100)]));
        $this->assertSame(1, DistributorOrder::query()->count());
        $this->assertSame([DistributorOrder::DELIVERED, 1], [$order->fresh()->status, $order->fresh()->lines()->count()]);
    }

    #[Test]
    public function every_distributor_with_an_api_has_its_own_tab_and_orders_are_added_by_hand(): void
    {
        $user = User::factory()->withPermissions(['distributor_orders' => ['view', 'create', 'update', 'delete'], 'order_references' => ['view']])->inTeam()->create();
        $this->actingAs($user);
        $rexel = Distributor::factory()->create(['name' => 'Rexel', 'price_provider' => 'rexel']);
        $wurth = Distributor::factory()->create(['name' => 'Würth', 'price_provider' => 'wurth']);
        $local = Distributor::factory()->create(['name' => 'Lokale winkel']);
        $reference = $this->reference(30, 11);

        Livewire::test(ListDistributorOrders::class)->assertOk()->assertSee('Rexel')->assertSee('Würth')->assertDontSee('Lokale winkel');
        $this->assertSame(['all', 'distributor-'.$rexel->id, 'distributor-'.$wurth->id], array_keys(Livewire::test(ListDistributorOrders::class)->instance()->getTabs()));

        Livewire::test(CreateDistributorOrder::class)
            ->fillForm([
                'distributor_id' => $wurth->id,
                'external_number' => 'W-77',
                'customer_reference' => 'Bestelling 030/1126/JL',
                'status' => DistributorOrder::CONFIRMED,
                'lines' => [['distributor_reference' => '0715 53 10', 'description' => 'Schroeven', 'quantity' => 200, 'quantity_delivered' => 0]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $order = DistributorOrder::query()->sole();
        $this->assertSame([DistributorOrder::SOURCE_MANUAL, $user->id], [$order->source, $order->created_by]);
        $this->assertTrue($order->orderReference->is($reference));

        Livewire::test(ListDistributorOrders::class)->set('activeTab', 'distributor-'.$wurth->id)->assertCanSeeTableRecords([$order]);
        Livewire::test(ListDistributorOrders::class)->set('activeTab', 'distributor-'.$rexel->id)->assertCanNotSeeTableRecords([$order]);
        $this->get(DistributorOrderResource::getUrl('view', ['record' => $order]))->assertOk()->assertSee('030/1126/JL')->assertSee('0715 53 10');

        // Fetching from Würth: prepared, not available yet.
        Livewire::test(ListDistributorOrders::class)->set('activeTab', 'distributor-'.$wurth->id)->callAction('sync')->assertNotified(__('erp.distributor_orders.sync_failed', ['name' => 'Würth']));

        // Orders from an API are read-only; without the permission there is nothing.
        $order->update(['source' => DistributorOrder::SOURCE_API]);
        $this->assertFalse($user->can('update', $order->fresh()));
        $this->actingAs(User::factory()->withPermissions([])->create())->get(DistributorOrderResource::getUrl())->assertForbidden();
    }

    #[Test]
    public function wurth_is_a_prepared_connection(): void
    {
        $this->assertSame('Würth', DistributorClients::options()['wurth']);
        $wurth = Distributor::factory()->create(['price_provider' => 'wurth', 'api_username' => 'pi', 'api_password' => 'geheim', 'api_customer_number' => '123']);
        $client = app(DistributorClients::class)->for($wurth);
        $this->assertInstanceOf(WurthClient::class, $client);
        $this->assertTrue($client->isConfigured());
        $this->assertFalse(app(SyncDistributorOrders::class)->supports($wurth));

        $this->expectException(DistributorException::class);
        $client->lookup(StockItem::factory()->create());
    }

    #[Test]
    public function stock_category_names_are_translated_from_dutch(): void
    {
        settings()->save('translation', ['deepl_key' => Crypt::encryptString('abc:fx')]);
        Http::fake(['api-free.deepl.com/*' => fn (Request $request) => Http::response(['translations' => array_map(fn (string $text): array => ['text' => ($request['target_lang'] === 'FR' ? 'FR ' : 'EN ').$text], $request['text'])])]);

        $category = StockCategory::query()->create(['name' => 'Kabels']);
        $this->assertSame(['FR Kabels', 'EN Kabels'], [$category->name_fr, $category->name_en]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'DeepL-Auth-Key abc:fx') && $request['source_lang'] === 'NL');

        // The Dutch name changes: translated again, except a language changed by hand at the same time.
        $category->update(['name' => 'Kabelgoten', 'name_fr' => 'Chemins de câbles']);
        $this->assertSame(['Chemins de câbles', 'EN Kabelgoten'], [$category->fresh()->name_fr, $category->fresh()->name_en]);

        // Shown in the user's language; the Dutch one when there is no translation.
        $sub = StockCategory::query()->create(['name' => 'Kabelgoten 60', 'parent_id' => $category->id, 'name_fr' => 'Goulottes 60']);
        app()->setLocale('fr');
        $this->assertSame('Chemins de câbles › Goulottes 60', $sub->fresh()->fullName());
        app()->setLocale('nl');

        // Without a key nothing is translated; "fill in missing translations" does it later.
        settings()->save('translation', ['deepl_key' => null]);
        $old = StockCategory::query()->create(['name' => 'Schakelaars']);
        $this->assertNull($old->name_fr);
        settings()->save('translation', ['deepl_key' => Crypt::encryptString('abc:fx')]);
        Livewire::actingAs(User::factory()->admin()->create())->test(ManageStockCategories::class)->callAction('translateMissing');
        $this->assertSame(['FR Schakelaars', 'EN Schakelaars'], [$old->fresh()->name_fr, $old->fresh()->name_en]);
    }
}
