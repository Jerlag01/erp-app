<?php

namespace App\Services\Distributors;

use App\Models\Distributor;
use App\Models\StockItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nussbaumer (nussbaumer.be): its webshop runs on Dynamicweb, whose own JSON interface (Headless Delivery
 * API, /dwapi) the shop exposes. With the webshop login of the distributor (Stock > Distributors, user and
 * password) the ERP signs in, searches the article and reads the customer's own price (without VAT), the
 * photo and the name: no web pages are read. The manufacturer reference is tried first and only taken when
 * the product's "Art. Ref." is that very reference; then Nussbaumer's own article number (e.g. A055585).
 */
class NussbaumerClient implements DistributorClient
{
    public const BASE = 'https://nussbaumer.be';

    /** The product index and query of the shop's search (Dynamicweb "Swift"). */
    public const REPOSITORY = 'Products';

    public const QUERY = 'Products';

    /** A login is valid 30 minutes; it is reused a little shorter. */
    public const TOKEN_MINUTES = 25;

    public function __construct(protected Distributor $distributor) {}

    public function isConfigured(): bool
    {
        return filled($this->distributor->api_username) && filled($this->distributor->api_password);
    }

    public function lookup(StockItem $item, ?string $reference = null): ?ProductInfo
    {
        if (! $this->isConfigured()) {
            throw new DistributorException(__('erp.stock.provider.not_configured', ['name' => 'Nussbaumer']));
        }

        $reference ??= $item->distributor_reference;

        // The manufacturer reference first ("Art. Ref." must be that reference), else Nussbaumer's number.
        foreach (array_values(array_unique(array_filter([$item->manufacturer_reference, $reference], 'filled'))) as $code) {
            $byNumber = $code === $reference;
            $product = collect($this->search((string) $code))->first(fn (array $product): bool => $byNumber
                ? StockItem::normaliseReference($product['number'] ?? null) === StockItem::normaliseReference($code)
                : StockItem::normaliseReference(self::field($product, 'Art_ref')) === StockItem::normaliseReference($code));

            if ($product !== null) {
                return self::productInfo($product);
            }
        }

        return null;
    }

    /**
     * The products the shop finds for a reference, with the prices of the signed-in customer.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $code): array
    {
        $response = $this->send('/dwapi/ecommerce/products/search', [
            'RepositoryName' => self::REPOSITORY,
            'QueryName' => self::QUERY,
            'q' => trim($code),
            'PageSize' => 10,
            'ShowPricesWithVat' => 'false',
        ]);

        return array_values(array_filter((array) $response->json('products'), 'is_array'));
    }

    /**
     * The customer's price without VAT (none when the shop shows no price), photo, name and Nussbaumer's number.
     *
     * @param  array<string, mixed>  $product
     */
    public static function productInfo(array $product): ProductInfo
    {
        $price = $product['price']['priceWithoutVat'] ?? $product['price']['price'] ?? null;
        $image = $product['defaultImage']['value'] ?? null;

        return new ProductInfo(
            price: is_numeric($price) && (float) $price > 0 ? round((float) $price, 4) : null,
            imageUrl: filled($image) ? (str_starts_with((string) $image, 'http') ? (string) $image : self::BASE.'/'.ltrim((string) $image, '/')) : null,
            name: filled($product['name'] ?? null) ? (string) $product['name'] : null,
            description: filled($product['shortDescription'] ?? null) ? strip_tags((string) $product['shortDescription']) : null,
            reference: filled($product['number'] ?? null) ? (string) $product['number'] : null,
        );
    }

    /**
     * A product field's value as text ("Art_ref" => "370R/10").
     *
     * @param  array<string, mixed>  $product
     */
    public static function field(array $product, string $name): ?string
    {
        $value = $product['productFields'][$name]['value'] ?? null;

        return is_scalar($value) && filled($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function send(string $path, array $query, bool $retried = false): Response
    {
        try {
            $response = Http::acceptJson()->timeout(30)->withToken($this->token())->get(self::BASE.$path, $query);
        } catch (ConnectionException $exception) {
            throw new DistributorException(__('erp.stock.provider.service_unreachable', ['name' => 'Nussbaumer']), previous: $exception);
        }

        // An expired login: sign in again once.
        if ($response->status() === 401 && ! $retried) {
            Cache::forget($this->cacheKey());

            return $this->send($path, $query, true);
        }

        if ($response->failed()) {
            $reason = 'HTTP '.$response->status();
            Log::warning('Nussbaumer API call failed', ['path' => $path, 'status' => $response->status()]);

            throw new DistributorException(__('erp.stock.provider.call_failed', ['name' => 'Nussbaumer', 'reason' => $reason]));
        }

        return $response;
    }

    /**
     * The token of the webshop login (kept 25 minutes per distributor and user).
     */
    protected function token(): string
    {
        return Cache::remember($this->cacheKey(), now()->addMinutes(self::TOKEN_MINUTES), function (): string {
            try {
                $response = Http::acceptJson()->asJson()->timeout(30)->post(self::BASE.'/dwapi/users/authenticate', [
                    'username' => (string) $this->distributor->api_username,
                    'password' => (string) $this->distributor->api_password,
                    'expirationInSeconds' => 1800,
                ]);
            } catch (ConnectionException $exception) {
                throw new DistributorException(__('erp.stock.provider.service_unreachable', ['name' => 'Nussbaumer']), previous: $exception);
            }

            if ($response->failed() || blank($response->json('token'))) {
                $reason = trim((string) ($response->json('title') ?: $response->json('detail') ?: 'HTTP '.$response->status()));
                Log::warning('Nussbaumer login refused', ['status' => $response->status(), 'reason' => $reason]);

                throw new DistributorException(__('erp.stock.provider.login_failed', ['name' => 'Nussbaumer', 'reason' => $reason]));
            }

            return (string) $response->json('token');
        });
    }

    protected function cacheKey(): string
    {
        return 'nussbaumer-token:'.$this->distributor->id.':'.hash('sha256', (string) $this->distributor->api_username.'|'.(string) $this->distributor->api_password);
    }
}
