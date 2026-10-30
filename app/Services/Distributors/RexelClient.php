<?php

namespace App\Services\Distributors;

use App\Models\Distributor;
use App\Models\StockItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Rexel Belgium's web services (SOAP 1.1, document/literal, see "Rexel web services" 2023-02):
 * GetPrices gives the customer's net price and availability, GetArticleInformation the description,
 * brand and image. Items are found by their Rexel item code or e-number (the distributor reference,
 * else the manufacturer reference); EAN codes are not accepted. Every call sends the customer number,
 * user and password of the distributor (Stock > Distributors). The answer is an XML string with a
 * status (0 = success) and the content; an unknown item comes back with an empty ItemCode.
 */
class RexelClient implements DistributorClient
{
    /** Production endpoint, unless services.rexel.url says otherwise (e.g. the test one). */
    public const ENDPOINT = 'https://services.rexel.be/exchange/ws/prod/rexel';

    public const NAMESPACE = 'rexel.ws.service';

    /** Status codes about the login, customer or set-up (Rexel's documentation). */
    public const LOGIN_ERRORS = [10, 11, 12, 13, 20, 21, 99];

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
            throw new DistributorException(__('erp.stock.provider.not_configured', ['name' => 'Rexel']));
        }

        foreach (array_values(array_unique(array_filter([$reference ?? $item->distributor_reference, $item->manufacturer_reference], 'filled'))) as $code) {
            $price = $this->prices([$code])[0] ?? null;

            if ($price === null) {
                continue;
            }

            $article = $this->articles([$price['code']])[0] ?? null;

            return new ProductInfo(
                // 0 means Rexel has no price for it: no price rather than a price of 0.
                price: ($price['net_price'] ?? 0) > 0 ? $price['net_price'] : null,
                imageUrl: $article['image'] ?? null,
                name: $article['name'] ?? $price['name'],
                description: $article['description'] ?? null,
            );
        }

        return null;
    }

    /**
     * Net price (for one unit) and availability per code, in the order asked; null for an unknown code.
     *
     * @param  list<string>  $codes  at most 100
     * @return list<array{code: string, name: ?string, net_price: ?float, base_price: ?float, surcharge: ?float, available: string|null, lead_time: ?int}|null>
     */
    public function prices(array $codes): array
    {
        $items = implode('', array_map(fn (string $code): string => '<itemData><itemCode>'.self::xml($code).'</itemCode><quantity>1</quantity></itemData>', array_slice($codes, 0, 100)));
        $content = $this->call('getPrices', $items);

        return array_map(function (SimpleXMLElement $detail): ?array {
            $code = trim((string) $detail->ItemCode);

            return $code === '' ? null : [
                'code' => $code,
                'name' => self::text($detail->ItemDescription),
                'net_price' => self::number($detail->NetPrice),
                'base_price' => self::number($detail->BasePrice),
                'surcharge' => self::number($detail->TotalSurcharge),
                'available' => self::text($detail->Available),
                'lead_time' => ($lead = self::text($detail->LeadTime)) === null ? null : (int) $lead,
            ];
        }, iterator_to_array($content->PriceDetail ?? [], false));
    }

    /**
     * Description, brand, image and links per code, in the order asked; null for an unknown code.
     *
     * @param  list<string>  $codes  at most 100
     * @return list<array{code: string, active: bool, name: ?string, description: ?string, brand: ?string, image: ?string, pdf: ?string, link: ?string}|null>
     */
    public function articles(array $codes): array
    {
        $items = implode('', array_map(fn (string $code): string => '<itemCodes>'.self::xml($code).'</itemCodes>', array_slice($codes, 0, 100)));
        $content = $this->call('getArticleInformation', $items);

        return array_map(function (SimpleXMLElement $detail): ?array {
            $code = trim((string) $detail->ItemCode);

            return $code === '' ? null : [
                'code' => $code,
                'active' => strtolower(trim((string) $detail->IsActive)) === 'true',
                'name' => self::text($detail->ItemDescription),
                'description' => self::text($detail->LongItemDescription),
                'brand' => self::text($detail->BrandDescription),
                'image' => self::text($detail->ImageLinkHigh) ?? self::text($detail->ImageLinkLow),
                'pdf' => self::text($detail->LinkToPDF),
                'link' => self::text($detail->DirectItemLink),
            ];
        }, iterator_to_array($content->ArticleDetail ?? [], false));
    }

    /**
     * Calls a method and returns the content section of its answer.
     *
     * @throws DistributorException on a failed call or a status other than 0
     */
    protected function call(string $method, string $items): SimpleXMLElement
    {
        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="'.self::NAMESPACE.'"><soapenv:Body>'
            ."<ser:{$method}>"
            .'<customer>'.self::xml($this->distributor->api_customer_number).'</customer>'
            .'<user>'.self::xml($this->distributor->api_username).'</user>'
            .'<password>'.self::xml($this->distributor->api_password).'</password>'
            .'<language>'.(app()->getLocale() === 'fr' ? 'FR' : 'NL').'</language>'
            .$items
            ."</ser:{$method}></soapenv:Body></soapenv:Envelope>";

        try {
            $response = Http::withBody($envelope, 'text/xml; charset=utf-8')
                ->withHeaders(['SOAPAction' => '""'])
                ->timeout(30)
                ->post((string) (config('services.rexel.url') ?: self::ENDPOINT));
        } catch (ConnectionException $exception) {
            throw new DistributorException(__('erp.stock.provider.service_unreachable', ['name' => 'Rexel']), previous: $exception);
        }

        $soap = @simplexml_load_string($response->body());
        $return = $soap === false ? null : ($soap->xpath('//*[local-name()="return"]')[0] ?? null);

        if (! $response->successful() || $return === null) {
            $fault = $soap === false ? null : (string) ($soap->xpath('//*[local-name()="faultstring"]')[0] ?? '');
            Log::warning('Rexel web service call failed', ['method' => $method, 'status' => $response->status(), 'fault' => $fault]);

            throw new DistributorException(__('erp.stock.provider.call_failed', ['name' => 'Rexel', 'reason' => $fault ?: 'HTTP '.$response->status()]));
        }

        $root = @simplexml_load_string((string) $return);

        if ($root === false) {
            throw new DistributorException(__('erp.stock.provider.call_failed', ['name' => 'Rexel', 'reason' => 'XML']));
        }

        $status = (int) $root->Status->StatusCode;

        if ($status !== 0) {
            $description = trim((string) $root->Status->StatusDescription);
            Log::warning('Rexel web service refused the call', ['method' => $method, 'status' => $status, 'description' => $description]);

            throw new DistributorException(in_array($status, self::LOGIN_ERRORS, true)
                ? __('erp.stock.provider.login_failed', ['name' => 'Rexel', 'reason' => "{$status}: {$description}"])
                : __('erp.stock.provider.call_failed', ['name' => 'Rexel', 'reason' => "{$status}: {$description}"]));
        }

        return $root->Content;
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

    protected static function number(?SimpleXMLElement $value): ?float
    {
        $text = trim((string) $value);

        return $text === '' || ! is_numeric($text) ? null : (float) $text;
    }
}
