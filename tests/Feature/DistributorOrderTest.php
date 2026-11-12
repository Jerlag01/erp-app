<?php

namespace Tests\Feature;

use App\Actions\Purchasing\ImportDistributorBasket;
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
use App\Services\Peppol\PeppolGateway;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
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

        // No connection lists placed orders yet: no "fetch orders" button.
        Livewire::test(ListDistributorOrders::class)->set('activeTab', 'distributor-'.$wurth->id)->assertActionHidden('sync');

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
        config(['services.translation.enabled' => true]);
        settings()->save('translation', ['enabled' => true, 'email' => 'info@example.com']);
        Http::fake(['api.mymemory.translated.net/*' => function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            // Answers like MyMemory's translation memory: lower case with a trailing ";".
            return Http::response(['responseData' => ['translatedText' => (str_ends_with($query['langpair'], 'fr-FR') ? 'fr ' : 'en ').mb_strtolower($query['q']).';'], 'responseStatus' => 200, 'quotaFinished' => false]);
        }]);

        $category = StockCategory::query()->create(['name' => 'Kabels']);
        $this->assertSame(['Fr kabels', 'En kabels'], [$category->name_fr, $category->name_en]);
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'langpair=nl|fr-FR') && str_contains(urldecode($request->url()), 'de=info@example.com'));

        // The Dutch name changes: translated again, except a language changed by hand at the same time.
        $category->update(['name' => 'Kabelgoten', 'name_fr' => 'Chemins de câbles']);
        $this->assertSame(['Chemins de câbles', 'En kabelgoten'], [$category->fresh()->name_fr, $category->fresh()->name_en]);

        // Shown in the user's language; the Dutch one when there is no translation.
        $sub = StockCategory::query()->create(['name' => 'Kabelgoten 60', 'parent_id' => $category->id, 'name_fr' => 'Goulottes 60']);
        app()->setLocale('fr');
        $this->assertSame('Chemins de câbles › Goulottes 60', $sub->fresh()->fullName());
        app()->setLocale('nl');

        // Switched off nothing is translated; "fill in missing translations" does it later.
        settings()->save('translation', ['enabled' => false, 'email' => null]);
        $old = StockCategory::query()->create(['name' => 'Schakelaars']);
        $this->assertNull($old->name_fr);
        settings()->save('translation', ['enabled' => true, 'email' => null]);
        Livewire::actingAs(User::factory()->admin()->create())->test(ManageStockCategories::class)->callAction('translateMissing');
        $this->assertSame(['Fr schakelaars', 'En schakelaars'], [$old->fresh()->name_fr, $old->fresh()->name_en]);
    }

    protected function ubl(string $number, ?string $orderReference, array $lines, string $vat = 'BE0123456749', ?string $salesOrder = null): string
    {
        $orderXml = $orderReference === null && $salesOrder === null ? '' : '<cac:OrderReference><cbc:ID>'.($orderReference ?? 'NA').'</cbc:ID>'.($salesOrder ? '<cbc:SalesOrderID>'.$salesOrder.'</cbc:SalesOrderID>' : '').'</cac:OrderReference>';
        $linesXml = implode('', array_map(fn (array $line, int $index): string => '<cac:InvoiceLine><cbc:ID>'.($index + 1).'</cbc:ID><cbc:InvoicedQuantity unitCode="'.($line[3] ?? 'C62').'">'.$line[1].'</cbc:InvoicedQuantity><cbc:LineExtensionAmount currencyID="EUR">'.($line[1] * $line[2]).'</cbc:LineExtensionAmount>'
            .'<cac:Item><cbc:Name>Artikel '.$line[0].'</cbc:Name><cac:SellersItemIdentification><cbc:ID>'.$line[0].'</cbc:ID></cac:SellersItemIdentification></cac:Item><cac:Price><cbc:PriceAmount currencyID="EUR">'.$line[2].'</cbc:PriceAmount></cac:Price></cac:InvoiceLine>', $lines, array_keys($lines)));

        return '<?xml version="1.0" encoding="UTF-8"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
            .'<cbc:ID>'.$number.'</cbc:ID><cbc:IssueDate>2026-11-10</cbc:IssueDate><cbc:BuyerReference>Werf Gent</cbc:BuyerReference>'.$orderXml
            .'<cac:AccountingSupplierParty><cac:Party><cac:PartyTaxScheme><cbc:CompanyID>'.$vat.'</cbc:CompanyID></cac:PartyTaxScheme><cac:PartyLegalEntity><cbc:RegistrationName>Rexel Belgium NV</cbc:RegistrationName></cac:PartyLegalEntity></cac:Party></cac:AccountingSupplierParty>'
            .'<cac:TaxTotal><cbc:TaxAmount currencyID="EUR">21</cbc:TaxAmount></cac:TaxTotal><cac:LegalMonetaryTotal><cbc:TaxExclusiveAmount currencyID="EUR">100</cbc:TaxExclusiveAmount><cbc:TaxInclusiveAmount currencyID="EUR">121</cbc:TaxInclusiveAmount></cac:LegalMonetaryTotal>'
            .$linesXml.'</Invoice>';
    }

    #[Test]
    public function peppol_invoices_of_a_distributor_record_what_was_delivered(): void
    {
        $rexel = Distributor::factory()->create(['name' => 'Rexel', 'price_provider' => 'rexel', 'vat_number' => 'BE 0123.456.749']);
        $reference = $this->reference(466, 10);
        $gateway = app(PeppolGateway::class);

        // Ordered through the basket earlier, with our number in the reference.
        $basket = DistributorOrder::query()->create(['distributor_id' => $rexel->id, 'customer_reference' => '466/1026/JL werf', 'status' => DistributorOrder::OPEN, 'source' => DistributorOrder::SOURCE_BASKET]);
        $basket->lines()->create(['line_number' => 1, 'distributor_reference' => 'XVB3G25', 'quantity' => 100]);
        $basket->lines()->create(['line_number' => 2, 'distributor_reference' => 'NIKO101', 'quantity' => 10]);

        // A first invoice delivers part of it.
        $first = $gateway->receive($this->ubl('F-1', '466/1026/JL werf', [['XVB3G25', 60, 1.5]]))['expense'];
        $basket->refresh();
        $this->assertSame([DistributorOrder::PARTIAL, $first->id], [$basket->status, $basket->expense_id]);
        $this->assertTrue($basket->orderReference->is($reference));
        $this->assertSame(60.0, (float) $basket->lines()->where('distributor_reference', 'XVB3G25')->value('quantity_delivered'));

        // The rest: delivered. The same invoice twice counts once.
        $gateway->receive($this->ubl('F-2', '466/1026/JL werf', [['XVB3G25', 40, 1.5], ['NIKO101', 10, 4.2]]));
        $this->assertSame(DistributorOrder::DELIVERED, $basket->fresh()->status);
        $this->assertSame('2026-11-10', $basket->fresh()->delivered_at->toDateString());
        $this->artisan('distributors:orders-from-invoices')->assertSuccessful();
        $this->assertSame(100.0, (float) $basket->lines()->where('distributor_reference', 'XVB3G25')->value('quantity_delivered'));

        // An invoice for an order we do not know yet: added as delivered, with the distributor's number.
        $gateway->receive($this->ubl('F-3', '470/1026/JL', [['ABC', 2, 10]], 'BE0123456749', '4500099'));
        $new = DistributorOrder::query()->where('external_number', '4500099')->sole();
        $this->assertSame([DistributorOrder::SOURCE_INVOICE, DistributorOrder::DELIVERED, null], [$new->source, $new->status, $new->order_reference_id]);
        $this->assertSame(20.0, (float) $new->total);
        $this->assertStringContainsString('F-3', $new->notes);

        // Our reference made later: linked then.
        $this->reference(470, 10);
        $this->assertNotNull($new->fresh()->order_reference_id);

        // Not from a distributor (another VAT number, even with "Rexel" in the name): nothing.
        $gateway->receive($this->ubl('X-1', '466/1026/JL', [['ABC', 1, 1]], 'BE0999999999'));
        $this->assertSame(2, DistributorOrder::query()->count());
    }

    #[Test]
    public function the_webshop_basket_is_imported_as_an_order(): void
    {
        $user = User::factory()->withPermissions(['distributor_orders' => ['view', 'create', 'update', 'delete']])->inTeam()->create();
        $this->actingAs($user);
        $reference = $this->reference(466, 10);
        $cebeo = Distributor::factory()->create(['name' => 'Cebeo', 'price_provider' => 'cebeo', 'api_username' => 'u', 'api_password' => 'p', 'api_customer_number' => '123456']);
        $rexel = Distributor::factory()->create(['name' => 'Rexel', 'price_provider' => 'rexel', 'api_username' => 'u', 'api_password' => 'p', 'api_customer_number' => '509']);

        $cebeoBasket = '<cebeoXML version="2.0.0"><Response><Message code="0"/><Order><Detail><OrderHeader><SupplierOrderID>0</SupplierOrderID><CustomerOrderID>Webshop</CustomerOrderID><CustomerOrderRef>Webshop</CustomerOrderRef><OrderDate><Day>20</Day><Month>10</Month><Year>2026</Year></OrderDate></OrderHeader>'
            .'<OrderLine><Material><SupplierItemID>4063318</SupplierItemID><Reference>700-85000</Reference><Description>Aanpaskader</Description></Material><UnitOfMeasure>PCE</UnitOfMeasure><UnitPrice><NetPrice>7,068</NetPrice></UnitPrice><OrderedQuantity>3</OrderedQuantity><BOQuantity>0</BOQuantity><BOEstimatedDeliveryDate><Day>25</Day><Month>11</Month><Year>2026</Year></BOEstimatedDeliveryDate><DeliveryQuantity>0</DeliveryQuantity></OrderLine></Detail></Order></Response></cebeoXML>';
        $rexelBasket = '<Root><Status><StatusCode>0</StatusCode></Status><Content><OrderLine><ItemCode>XVB3G25</ItemCode><SupplierItemCode>XVB-3G2.5</SupplierItemCode><OrderedQuantity>100</OrderedQuantity><ItemDescription>Kabel XVB</ItemDescription><SalesUnitCode>MTR</SalesUnitCode><NetPrice>150</NetPrice><NetAmount>150</NetAmount><LeadTime>2</LeadTime></OrderLine></Content></Root>';
        Http::fake([
            'b2b.cebeo.be/*' => Http::response($cebeoBasket),
            'services.rexel.be/*' => Http::response('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns:getBasketResponse xmlns:ns="rexel.ws.service"><return>'.htmlspecialchars($rexelBasket).'</return></ns:getBasketResponse></soap:Body></soap:Envelope>'),
        ]);

        Livewire::test(ListDistributorOrders::class)->set('activeTab', 'distributor-'.$cebeo->id)
            ->assertActionVisible('importBasket')->assertActionVisible('deliveryNote')
            ->callAction('importBasket', ['order_reference_id' => $reference->id, 'customer_reference' => '466/1026/JL'])
            ->assertNotified(__('erp.distributor_orders.basket_imported', ['count' => 1]));
        $order = DistributorOrder::query()->where('distributor_id', $cebeo->id)->sole();
        $line = $order->lines()->sole();
        $this->assertSame([DistributorOrder::SOURCE_BASKET, DistributorOrder::OPEN, $reference->id, $user->id], [$order->source, $order->status, $order->order_reference_id, $order->created_by]);
        $this->assertSame(['4063318', '700-85000', 3.0, 7.068, '2026-11-25'], [$line->distributor_reference, $line->manufacturer_reference, (float) $line->quantity, (float) $line->net_price, $line->expected_delivery->toDateString()]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '<OpenOrder/>') && str_contains($request->body(), '<ResponseType>Detail</ResponseType>'));

        // Rexel: the basket is emptied in NetStore, so the modal warns; the price per unit from the amount.
        Livewire::test(ListDistributorOrders::class)->set('activeTab', 'distributor-'.$rexel->id)->assertActionHidden('deliveryNote')
            ->callAction('importBasket');
        $this->assertTrue(app(ImportDistributorBasket::class)->source($rexel)->basketIsEmptied());
        $this->assertFalse(app(ImportDistributorBasket::class)->source($cebeo)->basketIsEmptied());
        $rexelLine = DistributorOrder::query()->where('distributor_id', $rexel->id)->sole()->lines()->sole();
        $this->assertSame(['XVB3G25', 1.5, 'MTR'], [$rexelLine->distributor_reference, (float) $rexelLine->net_price, $rexelLine->unit]);
    }

    #[Test]
    public function a_cebeo_delivery_note_is_recorded_on_its_order(): void
    {
        $user = User::factory()->withPermissions(['distributor_orders' => ['view', 'create', 'update', 'delete']])->inTeam()->create();
        $this->actingAs($user);
        $cebeo = Distributor::factory()->create(['name' => 'Cebeo', 'price_provider' => 'cebeo', 'api_username' => 'u', 'api_password' => 'p', 'api_customer_number' => '123456']);
        $order = DistributorOrder::query()->create(['distributor_id' => $cebeo->id, 'customer_reference' => '466/1026/JL', 'status' => DistributorOrder::OPEN, 'source' => DistributorOrder::SOURCE_BASKET]);
        $order->lines()->create(['line_number' => 1, 'distributor_reference' => '4063318', 'quantity' => 3]);
        $note = '<cebeoXML version="2.0.0"><Response><Message code="0"/><DispatchAdvice><Detail><DispatchAdviceHeader><DispatchAdviceID>778899</DispatchAdviceID><DeliveryDate><Day>12</Day><Month>11</Month><Year>2026</Year></DeliveryDate></DispatchAdviceHeader>'
            .'<DispatchAdviceLine><SupplierOrderID>5550001</SupplierOrderID><CustomerOrderID>466/1026/JL</CustomerOrderID><SupplierOrderLineID>1</SupplierOrderLineID><CustomerOrderLineID/><Material><SupplierItemID>4063318</SupplierItemID></Material><UnitPrice><NetPrice>7,068</NetPrice></UnitPrice><DeliveryQuantity>3</DeliveryQuantity></DispatchAdviceLine></Detail></DispatchAdvice></Response></cebeoXML>';
        Http::fake(['b2b.cebeo.be/*' => Http::sequence()->push($note)->push($note)->push('<cebeoXML version="2.0.0"><Response><Message code="1">Error:</Message></Response></cebeoXML>')]);

        $page = Livewire::test(ListDistributorOrders::class)->set('activeTab', 'distributor-'.$cebeo->id);
        $page->callAction('deliveryNote', ['number' => '778899'])->assertNotified(__('erp.distributor_orders.delivery_note_recorded', ['count' => 1]));
        $order->refresh();
        $this->assertSame([DistributorOrder::DELIVERED, '5550001', '2026-11-12'], [$order->status, $order->external_number, $order->delivered_at->toDateString()]);

        // Twice: nothing changes. An unknown number is said so.
        $page->callAction('deliveryNote', ['number' => '778899'])->assertNotified(__('erp.distributor_orders.delivery_note_done', ['number' => '778899']));
        $this->assertSame(3.0, (float) $order->lines()->sole()->quantity_delivered);
        $page->callAction('deliveryNote', ['number' => '0012345678901'])->assertHasNoActionErrors()->assertNotified(__('erp.distributor_orders.delivery_note_unknown', ['number' => '12345678901']));
    }
}
