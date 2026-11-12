<?php

namespace App\Services\Distributors;

use App\Models\Distributor;
use App\Models\StockItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Cebeo's B2B XML web services (cebeoXML 2.0.0, see b2b.cebeo.be/nl/webservices): a Cebeo-defined XML
 * message posted to one address, without SOAP or WSDL. Every request carries the customer number, user
 * and password of the distributor (Stock > Distributors). Items are fetched by their Cebeo article number
 * (Article/Get on the distributor reference), else searched by the manufacturer reference
 * (Article/Search). The answer gives the customer's net price per unit (decimal comma), the
 * description and the stock; it has no pictures. Message code 0 is success; "ART0011" means not found.
 *
 * Orders: there is no call that lists or searches the orders (Order/Search is not implemented), and
 * Order/Get by our CustomerOrderID returns the open order instead. Order/Get by Cebeo's order number
 * (SupplierOrderID) does work, although the schema calls it "not implemented": it gives the order with
 * per line the ordered, delivered and back-ordered quantity and the dates. Order numbers come from the
 * basket (the open order already has its number), delivery notes (DispatchAdvice/Get), Peppol invoices
 * and the order confirmations and delivery notes Cebeo can POST to us (parseDocument).
 */
class CebeoClient implements DistributorBasketSource, DistributorClient, DistributorOrderLookup
{
    public const ENDPOINT = 'https://b2b.cebeo.be/webservices/xml';

    public const VERSION = '2.0.0';

    public const NOT_FOUND = ['ART0011'];

    public function __construct(protected Distributor $distributor) {}

    public function isConfigured(): bool
    {
        return filled($this->distributor->api_username)
            && filled($this->distributor->api_password)
            && filled($this->distributor->api_customer_number);
    }

    public function lookup(StockItem $item, ?string $reference = null): ?ProductInfo
    {
        if (! $this->isConfigured()) {
            throw new DistributorException(__('erp.stock.provider.not_configured', ['name' => 'Cebeo']));
        }

        $reference ??= $item->distributor_reference;
        $article = filled($reference) ? ($this->articles([(string) $reference])[0] ?? null) : null;

        if ($article === null && filled($item->manufacturer_reference)) {
            $article = $this->search((string) $item->manufacturer_reference);
        }

        // An article that is no longer sold comes back with price 0: no price rather than a price of 0.
        return $article === null ? null : new ProductInfo(price: ($article['net_price'] ?? 0) > 0 ? $article['net_price'] : null, name: $article['description']);
    }

    /**
     * The articles with these Cebeo article numbers (unknown ones left out).
     *
     * @param  list<string>  $codes
     * @return list<array{code: string, reference: ?string, brand: ?string, description: ?string, unit: ?string, net_price: ?float, tarif_price: ?float, stock: ?int, sales_pack: ?int}>
     */
    public function articles(array $codes): array
    {
        $materials = implode('', array_map(fn (string $code): string => '<Material><SupplierItemID>'.self::xml($code).'</SupplierItemID></Material>', array_slice($codes, 0, 50)));

        return $this->items($this->call("<Article><Get>{$materials}</Get></Article>"));
    }

    /**
     * Searches like the e-shop; the article whose manufacturer reference matches (with a price and the
     * most stock when several do), else the only result.
     *
     * @return array{code: string, reference: ?string, brand: ?string, description: ?string, unit: ?string, net_price: ?float, tarif_price: ?float, stock: ?int, sales_pack: ?int}|null
     */
    public function search(string $reference): ?array
    {
        $found = $this->items($this->call('<Article><Search><SearchKeywords><Keyword>'.self::xml($reference).'</Keyword></SearchKeywords></Search></Article>'));
        $normalise = fn (?string $value): string => strtoupper(preg_replace('/[\s.\-\/]/', '', (string) $value));

        // Several articles can share a reference (reel lengths, old numbers): the one with a price and the most stock.
        $matching = collect($found)->filter(fn (array $article): bool => $normalise($article['reference']) === $normalise($reference))
            ->sortByDesc(fn (array $article): array => [($article['net_price'] ?? 0) > 0 ? 1 : 0, $article['stock'] ?? 0])
            ->values();

        return $matching->first() ?? (count($found) === 1 ? $found[0] : null);
    }

    public function basketIsEmptied(): bool
    {
        return false;
    }

    /**
     * The open order in the Cebeo e-shop (the basket) with its lines and Cebeo's order number.
     */
    public function basket(): ?OrderInfo
    {
        $this->ensureConfigured();
        $detail = $this->call('<Order><Get><OpenOrder/></Get></Order>', 'Detail')?->Order->Detail;

        return $detail === null || ! isset($detail->OrderLine) ? null : self::orderFromDetail($detail);
    }

    /**
     * An order by Cebeo's order number; null when Cebeo does not know it.
     */
    public function order(string $number): ?OrderInfo
    {
        $this->ensureConfigured();
        $detail = $this->call('<Order><Get><SupplierOrderID>'.self::xml($number).'</SupplierOrderID></Get></Order>', 'Detail', true)?->Order->Detail;

        if ($detail === null || ! isset($detail->OrderHeader)) {
            return null;
        }

        $order = self::orderFromDetail($detail);

        // An unknown number may come back as the open order.
        return $order->number === ltrim($number, '0') ? $order : null;
    }

    /**
     * A delivery note by its number: per Cebeo order on it, the delivered lines, with Cebeo's order number
     * and our order number (CustomerOrderID).
     *
     * @return list<OrderInfo>
     */
    public function dispatchAdvice(string $number): array
    {
        $this->ensureConfigured();
        $detail = $this->call('<DispatchAdvice><Get><DispatchAdviceID>'.self::xml($number).'</DispatchAdviceID></Get></DispatchAdvice>', 'Detail', true)?->DispatchAdvice->Detail;

        return $detail === null || ! isset($detail->DispatchAdviceLine) ? [] : self::ordersFromDispatch($detail, $number);
    }

    /**
     * A document Cebeo POSTs to us (order confirmation or delivery note in cebeoXML).
     *
     * @return array{orders: list<OrderInfo>, delivery_note: ?string}
     *
     * @throws DistributorException when it is not one of those
     */
    public static function parseDocument(string $xml): array
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new DistributorException('Documents may not contain a DOCTYPE.');
        }

        $root = @simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);

        if ($root === false) {
            throw new DistributorException('Not a valid XML document.');
        }

        if ($order = $root->xpath('//Order/Detail')[0] ?? null) {
            return ['orders' => [self::orderFromDetail($order)], 'delivery_note' => null];
        }

        if ($dispatch = $root->xpath('//DispatchAdvice/Detail')[0] ?? null) {
            $number = self::text($dispatch->DispatchAdviceHeader->DispatchAdviceID) ?? '';

            return ['orders' => self::ordersFromDispatch($dispatch, $number), 'delivery_note' => $number];
        }

        throw new DistributorException('Not a cebeoXML order confirmation or delivery note.');
    }

    /**
     * An Order/Detail: the status follows the lines (all delivered, partly, or not yet); an order that
     * is not closed in the e-shop ("Niet afgesloten order") is still open.
     */
    public static function orderFromDetail(SimpleXMLElement $detail): OrderInfo
    {
        $header = $detail->OrderHeader;
        $lines = array_map(fn (SimpleXMLElement $line): OrderLineInfo => new OrderLineInfo(
            distributorReference: self::text($line->Material->SupplierItemID),
            manufacturerReference: self::text($line->Material->Reference),
            description: self::text($line->Material->Description),
            quantity: (float) self::number($line->OrderedQuantity),
            quantityDelivered: (float) self::number($line->DeliveryQuantity),
            unit: self::text($line->UnitOfMeasure),
            netPrice: self::number($line->UnitPrice->NetPrice),
            expectedDelivery: (float) self::number($line->BOQuantity) > 0
                ? (self::date($line->BOEstimatedDeliveryDate) ?? self::date($line->RequestedDeliveryDate))
                : (self::date($line->RequestedDeliveryDate) ?? self::date($line->BOEstimatedDeliveryDate)),
            deliveredAt: self::date($line->DeliveryDate),
        ), iterator_to_array($detail->OrderLine ?? [], false));

        $statusText = self::text($header->OrderStatus) ?? '';
        $delivered = $lines !== [] && collect($lines)->every(fn (OrderLineInfo $line): bool => $line->quantity > 0 && $line->quantityDelivered >= $line->quantity);
        $status = match (true) {
            (bool) preg_match('/annul|vervallen|geschrapt/i', $statusText) => 'cancelled',
            $delivered => 'delivered',
            collect($lines)->contains(fn (OrderLineInfo $line): bool => $line->quantityDelivered > 0) => 'partial',
            (bool) preg_match('/niet afgesloten/i', $statusText) => 'open',
            default => 'confirmed',
        };
        $open = collect($lines)->filter(fn (OrderLineInfo $line): bool => $line->quantityDelivered < $line->quantity);
        $ourNumber = self::text($header->CustomerOrderID);
        $reference = self::text($header->CustomerOrderRef);

        return new OrderInfo(
            number: ltrim((string) self::text($header->SupplierOrderID), '0'),
            customerReference: collect([$ourNumber, $reference])->filter()->unique()->implode(' · ') ?: null,
            orderedAt: self::date($header->OrderDate),
            status: $status,
            expectedDelivery: $open->map(fn (OrderLineInfo $line) => $line->expectedDelivery)->filter()->max(),
            deliveredAt: $delivered ? collect($lines)->map(fn (OrderLineInfo $line) => $line->deliveredAt)->filter()->max() : null,
            total: round(array_sum(array_map(fn (OrderLineInfo $line): float => $line->quantity * (float) $line->netPrice, $lines)), 2),
            lines: $lines,
            raw: array_filter(['status_text' => $statusText, 'ordered_by' => self::text($header->OrderedBy)]),
        );
    }

    /**
     * A DispatchAdvice/Detail: per Cebeo order on it, the delivered lines.
     *
     * @return list<OrderInfo>
     */
    public static function ordersFromDispatch(SimpleXMLElement $detail, string $number): array
    {
        $date = self::date($detail->DispatchAdviceHeader->DeliveryDate) ?? today();
        $orders = [];

        foreach ($detail->DispatchAdviceLine as $line) {
            $key = ltrim((string) self::text($line->SupplierOrderID), '0');
            $orders[$key]['customer'] ??= self::text($line->CustomerOrderID);
            $orders[$key]['lines'][] = new OrderLineInfo(
                distributorReference: self::text($line->Material->SupplierItemID),
                manufacturerReference: self::text($line->Material->Reference),
                description: self::text($line->Material->Description),
                quantity: (float) self::number($line->DeliveryQuantity),
                quantityDelivered: (float) self::number($line->DeliveryQuantity),
                netPrice: self::number($line->UnitPrice->NetPrice),
                deliveredAt: $date,
            );
        }

        return array_map(fn (string|int $key): OrderInfo => new OrderInfo(
            number: (string) $key,
            customerReference: $orders[$key]['customer'],
            status: 'delivered',
            deliveredAt: $date,
            lines: $orders[$key]['lines'],
            raw: ['delivery_note' => $number],
        ), array_keys($orders));
    }

    protected function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new DistributorException(__('erp.stock.provider.not_configured', ['name' => 'Cebeo']));
        }
    }

    /**
     * cebeoXML dates: <Day/><Month/><Year/>.
     */
    protected static function date(?SimpleXMLElement $value): ?Carbon
    {
        if ($value === null || ! isset($value->Year)) {
            return null;
        }

        return checkdate((int) $value->Month, (int) $value->Day, (int) $value->Year)
            ? Carbon::create((int) $value->Year, (int) $value->Month, (int) $value->Day)->startOfDay()
            : null;
    }

    /**
     * @return list<array{code: string, reference: ?string, brand: ?string, description: ?string, unit: ?string, net_price: ?float, tarif_price: ?float, stock: ?int, sales_pack: ?int}>
     */
    protected function items(?SimpleXMLElement $response): array
    {
        if ($response === null) {
            return [];
        }

        return array_values(array_filter(array_map(function (SimpleXMLElement $item): ?array {
            $code = self::text($item->Material->SupplierItemID);

            return $code === null ? null : [
                'code' => $code,
                'reference' => self::text($item->Material->Reference),
                'brand' => self::text($item->Material->BrandName),
                'description' => self::text($item->Material->Description),
                'unit' => self::text($item->UnitOfMeasure),
                'net_price' => self::number($item->UnitPrice->NetPrice),
                'tarif_price' => self::number($item->UnitPrice->TarifPrice),
                'stock' => ($stock = self::text($item->Stock)) === null ? null : (int) $stock,
                'sales_pack' => ($pack = self::text($item->SalesPackQuantity)) === null ? null : (int) $pack,
            ];
        }, iterator_to_array($response->Article->List->Item ?? [], false))));
    }

    /**
     * Posts a request and returns its Response element; null when the article is not found.
     *
     * @throws DistributorException on a failed call or an error message
     */
    protected function call(string $operation, string $responseType = 'List', bool $unknownIsEmpty = false): ?SimpleXMLElement
    {
        $request = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<cebeoXML xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://b2b.cebeo.be/XMLschema/'.self::VERSION.'/cebeoXML.xsd" version="'.self::VERSION.'">'
            .'<Request><Customer>'
            .'<CustomerNumber>'.self::xml($this->distributor->api_customer_number).'</CustomerNumber>'
            .'<UserName>'.self::xml($this->distributor->api_username).'</UserName>'
            .'<Password>'.self::xml($this->distributor->api_password).'</Password>'
            .'</Customer><ResponseType>'.$responseType.'</ResponseType>'
            .$operation
            .'</Request></cebeoXML>';

        try {
            $response = Http::withBody($request, 'application/xml; charset=utf-8')
                ->timeout(30)
                ->post((string) (config('services.cebeo.url') ?: self::ENDPOINT));
        } catch (ConnectionException $exception) {
            throw new DistributorException(__('erp.stock.provider.service_unreachable', ['name' => 'Cebeo']), previous: $exception);
        }

        $root = @simplexml_load_string($response->body());

        if (! $response->successful() || $root === false || ! isset($root->Response)) {
            Log::warning('Cebeo web service call failed', ['status' => $response->status()]);

            throw new DistributorException(__('erp.stock.provider.call_failed', ['name' => 'Cebeo', 'reason' => 'HTTP '.$response->status()]));
        }

        $message = $root->Response->Message;
        $code = (int) ($message['code'] ?? 0);
        $text = trim((string) $message);

        if ($code === 0) {
            return $root->Response;
        }

        // An unknown delivery note gives only "1: Error:", an unknown order "1: Error: … Unknown Order".
        if (in_array($text, self::NOT_FOUND, true) || ($unknownIsEmpty && $code === 1 && (rtrim($text, ': ') === 'Error' || stripos($text, 'Unknown Order') !== false))) {
            return null;
        }

        Log::warning('Cebeo web service refused the call', ['code' => $code, 'message' => $text]);

        throw new DistributorException($code === 1
            ? __('erp.stock.provider.login_failed', ['name' => 'Cebeo', 'reason' => "{$code}: {$text}"])
            : __('erp.stock.provider.call_failed', ['name' => 'Cebeo', 'reason' => "{$code}: {$text}"]));
    }

    protected static function xml(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected static function text(?SimpleXMLElement $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * "2,3810" (Cebeo writes decimal commas).
     */
    protected static function number(?SimpleXMLElement $value): ?float
    {
        $text = str_replace(',', '.', trim((string) $value));

        return $text === '' || ! is_numeric($text) ? null : (float) $text;
    }
}
