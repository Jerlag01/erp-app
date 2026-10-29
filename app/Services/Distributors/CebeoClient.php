<?php

namespace App\Services\Distributors;

use App\Models\Distributor;
use App\Models\StockItem;
use Illuminate\Http\Client\ConnectionException;
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
 */
class CebeoClient implements DistributorClient
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

    public function lookup(StockItem $item): ?ProductInfo
    {
        if (! $this->isConfigured()) {
            throw new DistributorException(__('erp.stock.provider.not_configured', ['name' => 'Cebeo']));
        }

        $article = filled($item->distributor_reference) ? ($this->articles([(string) $item->distributor_reference])[0] ?? null) : null;

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
    protected function call(string $operation): ?SimpleXMLElement
    {
        $request = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<cebeoXML xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://b2b.cebeo.be/XMLschema/'.self::VERSION.'/cebeoXML.xsd" version="'.self::VERSION.'">'
            .'<Request><Customer>'
            .'<CustomerNumber>'.self::xml($this->distributor->api_customer_number).'</CustomerNumber>'
            .'<UserName>'.self::xml($this->distributor->api_username).'</UserName>'
            .'<Password>'.self::xml($this->distributor->api_password).'</Password>'
            .'</Customer><ResponseType>List</ResponseType>'
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

        if (in_array($text, self::NOT_FOUND, true)) {
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
