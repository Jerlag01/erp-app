<?php

namespace App\Services\Distributors;

use App\Models\Distributor;
use App\Models\StockItem;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DigiKey's REST APIs (developer.digikey.com, OAuth 2.0), with the app's Client ID (user name) and
 * Client Secret (password) of the distributor (Stock > Distributors):
 *
 * - Product Information V4: price (the account's own price when there is one, else the list price for
 *   one piece, in EUR for Belgium), photo and description, by manufacturer reference or DigiKey number.
 *   Works with the app login alone (2-legged).
 * - Order Status V4: the orders of the account (the customer number field = DigiKey's Account ID) and
 *   one sales order by its number. One ERP order per DigiKey sales order.
 * - Barcode V3 and the Customer Resource (associated accounts) need a DigiKey user login (3-legged):
 *   "Connect with DigiKey" on the distributor keeps its refresh token (valid 90 days, renewed on use).
 */
class DigiKeyClient implements DistributorClient, DistributorOrderLookup, DistributorOrderSource
{
    public const BASE = 'https://api.digikey.com';

    public const TOKEN_URL = self::BASE.'/v1/oauth2/token';

    public const AUTHORIZE_URL = self::BASE.'/v1/oauth2/authorize';

    /** Order Status pages hold at most 25 orders. */
    public const PAGE_SIZE = 25;

    /** First sync: the orders of the last 90 days. */
    public const HISTORY_DAYS = 90;

    public function __construct(protected Distributor $distributor) {}

    public function isConfigured(): bool
    {
        return filled($this->distributor->api_username) && filled($this->distributor->api_password);
    }

    /**
     * Whether a DigiKey user login is connected (needed for barcodes and associated accounts).
     */
    public function isConnected(): bool
    {
        return filled($this->distributor->api_refresh_token)
            && ($this->distributor->api_refresh_expires_at === null || $this->distributor->api_refresh_expires_at->isFuture());
    }

    public function lookup(StockItem $item, ?string $reference = null): ?ProductInfo
    {
        $this->ensureConfigured();
        $reference ??= $item->distributor_reference;

        // The manufacturer reference first (only when DigiKey returns that very reference), else DigiKey's number.
        foreach (array_values(array_unique(array_filter([$item->manufacturer_reference, $reference], 'filled'))) as $code) {
            $product = $this->product((string) $code);

            if ($product === null) {
                continue;
            }

            $byManufacturer = $code === $item->manufacturer_reference && $code !== $reference;

            if ($byManufacturer && StockItem::normaliseReference($product['ManufacturerProductNumber'] ?? null) !== StockItem::normaliseReference($code)) {
                continue;
            }

            return self::productInfo($product, $code === $reference ? (string) $reference : null);
        }

        return null;
    }

    /**
     * The product details (Product Information V4); null when DigiKey does not know the number.
     *
     * @return array<string, mixed>|null
     */
    public function product(string $number): ?array
    {
        $response = $this->send('get', '/products/v4/search/'.rawurlencode(trim($number)).'/productdetails', notFoundIsEmpty: true);

        return $response === null ? null : ($response->json('Product') ?: null);
    }

    /**
     * Price, image and names of a product; the price of the package type with this DigiKey number, else
     * of the one that can be bought per piece (cut tape, bulk) rather than a whole reel.
     *
     * @param  array<string, mixed>  $product
     */
    public static function productInfo(array $product, ?string $digiKeyNumber = null): ProductInfo
    {
        $variations = collect($product['ProductVariations'] ?? [])->reject(fn (array $variation): bool => (bool) ($variation['MarketPlace'] ?? false));
        $variation = $variations->first(fn (array $variation): bool => filled($digiKeyNumber) && strcasecmp((string) $variation['DigiKeyProductNumber'], (string) $digiKeyNumber) === 0)
            ?? $variations->sortBy(fn (array $variation): int => (int) ($variation['MinimumOrderQuantity'] ?? PHP_INT_MAX))->first();
        $price = collect($variation['MyPricing'] ?? [])->sortBy('BreakQuantity')->first()['UnitPrice']
            ?? collect($variation['StandardPricing'] ?? [])->sortBy('BreakQuantity')->first()['UnitPrice']
            ?? $product['UnitPrice'] ?? null;

        return new ProductInfo(
            price: $price !== null && (float) $price > 0 ? round((float) $price, 4) : null,
            imageUrl: $product['PhotoUrl'] ?? null,
            name: $product['Description']['ProductDescription'] ?? null,
            description: $product['Description']['DetailedDescription'] ?? null,
            reference: $variation['DigiKeyProductNumber'] ?? null,
        );
    }

    public function supportsOrders(): bool
    {
        return $this->isConfigured() && filled($this->distributor->api_customer_number);
    }

    /**
     * The sales orders of the account entered since a day before $since (the last 90 days the first time).
     *
     * @return iterable<OrderInfo>
     */
    public function orders(?CarbonInterface $since = null): iterable
    {
        $this->ensureOrders();
        $start = ($since ? Carbon::parse($since)->subDay() : now()->subDays(self::HISTORY_DAYS))->toDateString();

        for ($page = 1; $page <= 40; $page++) {
            $data = $this->send('get', '/orderstatus/v4/orders', [
                'Shared' => 'true',
                'StartDate' => $start,
                'EndDate' => now()->toDateString(),
                'PageNumber' => $page,
                'PageSize' => self::PAGE_SIZE,
            ])?->json() ?? [];

            foreach ($data['Orders'] ?? [] as $order) {
                foreach ($order['SalesOrders'] ?? [] as $salesOrder) {
                    yield self::orderInfo($salesOrder, $order);
                }
            }

            if ($page * self::PAGE_SIZE >= (int) ($data['TotalOrders'] ?? 0)) {
                return;
            }
        }
    }

    /**
     * One sales order by its number; null when DigiKey does not know it.
     */
    public function order(string $number): ?OrderInfo
    {
        $this->ensureOrders();

        if (! ctype_digit($number = trim($number))) {
            return null;
        }

        $salesOrder = $this->send('get', '/orderstatus/v4/salesorder/'.$number, notFoundIsEmpty: true)?->json();

        return is_array($salesOrder) && isset($salesOrder['SalesOrderId']) ? self::orderInfo($salesOrder) : null;
    }

    /**
     * A sales order as an ERP order: shipped quantities are delivered, a fully shipped order is delivered.
     *
     * @param  array<string, mixed>  $salesOrder
     * @param  array<string, mixed>  $order  the web order around it, when known
     */
    public static function orderInfo(array $salesOrder, array $order = []): OrderInfo
    {
        $lines = [];
        $shippedDates = [];
        $expected = [];

        foreach ($salesOrder['LineItems'] ?? [] as $line) {
            $shipments = collect($line['ItemShipments'] ?? []);
            $shipped = (float) ($line['QuantityShipped'] ?? $shipments->sum('QuantityShipped'));
            $ordered = (float) ($line['QuantityOrdered'] ?? $line['QuantityInitialRequested'] ?? 0);
            $lastShipped = self::date($shipments->pluck('ShippedDate')->filter()->sort()->last());
            $nextDate = $shipped >= $ordered
                ? null
                : self::date(collect($line['Schedules'] ?? [])->pluck('ScheduledDate')->filter()->sort()->first()
                    ?? $shipments->pluck('ExpectedDeliveryDate')->filter()->sort()->last());

            if ($lastShipped !== null) {
                $shippedDates[] = $lastShipped;
            }

            if ($nextDate !== null) {
                $expected[] = $nextDate;
            }

            $lines[] = new OrderLineInfo(
                distributorReference: $line['DigiKeyProductNumber'] ?? null,
                manufacturerReference: $line['ManufacturerProductNumber'] ?? null,
                description: filled($line['Description'] ?? null) ? Str::limit((string) $line['Description'], 250, '') : null,
                quantity: $ordered,
                quantityDelivered: min($shipped, $ordered ?: $shipped),
                unit: $line['PackType'] ?? null,
                netPrice: isset($line['UnitPrice']) ? (float) $line['UnitPrice'] : null,
                expectedDelivery: $nextDate,
                deliveredAt: $shipped > 0 && $shipped >= $ordered ? $lastShipped : null,
            );
        }

        $code = (string) ($salesOrder['Status']['SalesOrderStatus'] ?? $order['EntireOrderStatus']['OrderStatus'] ?? 'Unknown');
        $allShipped = $lines !== [] && collect($lines)->every(fn (OrderLineInfo $line): bool => $line->quantity > 0 && $line->quantityDelivered >= $line->quantity);
        $anyShipped = collect($lines)->contains(fn (OrderLineInfo $line): bool => $line->quantityDelivered > 0);

        $status = match (true) {
            $code === 'Canceled' => 'cancelled',
            $code === 'Delivered', $allShipped => 'delivered',
            $anyShipped || str_contains($code, 'Partial') => 'partial',
            in_array($code, ['Unknown', 'Proforma', 'ActionRequiredWireTransfer'], true) => 'open',
            default => 'confirmed',
        };

        $reference = trim(implode(' · ', array_unique(array_filter([$salesOrder['PurchaseOrder'] ?? null, $order['PONumber'] ?? null], 'filled'))));
        sort($shippedDates);
        sort($expected);

        return new OrderInfo(
            number: (string) $salesOrder['SalesOrderId'],
            customerReference: $reference !== '' ? Str::limit($reference, 250, '') : null,
            orderedAt: self::date($salesOrder['DateEntered'] ?? $order['DateEntered'] ?? null),
            status: $status,
            expectedDelivery: $status === 'delivered' ? null : ($expected[0] ?? null),
            deliveredAt: $status === 'delivered' ? (end($shippedDates) ?: null) : null,
            total: isset($salesOrder['TotalPrice']) ? round((float) $salesOrder['TotalPrice'], 2) : null,
            lines: $lines,
            raw: array_filter([
                'status_text' => $salesOrder['Status']['ShortDescription'] ?? $order['EntireOrderStatus']['ShortDescription'] ?? $code,
                'order_number' => isset($order['OrderNumber']) || isset($salesOrder['OrderNumber']) ? (string) ($salesOrder['OrderNumber'] ?? $order['OrderNumber']) : null,
                'ordered_by' => trim(($salesOrder['Contact']['FirstName'] ?? '').' '.($salesOrder['Contact']['LastName'] ?? '')) ?: null,
                'tracking' => collect($salesOrder['LineItems'] ?? [])->flatMap(fn (array $line): array => array_column($line['ItemShipments'] ?? [], 'TrackingNumber'))->filter()->unique()->values()->all() ?: null,
            ], fn ($value): bool => $value !== null),
        );
    }

    /**
     * What a scanned DigiKey label or packing slip says (Barcode V3, needs the user login): the product
     * (DigiKey and manufacturer number, quantity) or the sales order on a packing slip.
     *
     * @return array{digikey: ?string, manufacturer_reference: ?string, manufacturer: ?string, description: ?string, quantity: ?int, sales_order: ?string}|null
     */
    public function scan(string $barcode): ?array
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            return null;
        }

        // The 2D code (ISO 15434, "[)>") holds the numbers itself: no call needed.
        if (($parsed = self::parse2D($barcode)) !== null) {
            return $parsed;
        }

        $this->ensureConfigured();
        $paths = str_starts_with($barcode, '[)>')
            ? ['/Barcoding/v3/Product2DBarcodes/', '/Barcoding/v3/PackList2DBarcodes/']
            : ['/Barcoding/v3/ProductBarcodes/', '/Barcoding/v3/PackListBarcodes/'];

        foreach ($paths as $path) {
            $data = $this->send('get', $path.rawurlencode($barcode), user: true, notFoundIsEmpty: true)?->json();

            if (is_array($data) && (filled($data['DigiKeyPartNumber'] ?? null) || filled($data['SalesorderId'] ?? null))) {
                return [
                    'digikey' => $data['DigiKeyPartNumber'] ?? ($data['PackListDetails'][0]['DigiKeyPartNumber'] ?? null),
                    'manufacturer_reference' => $data['ManufacturerPartNumber'] ?? null,
                    'manufacturer' => $data['ManufacturerName'] ?? null,
                    'description' => $data['ProductDescription'] ?? null,
                    'quantity' => isset($data['Quantity']) ? (int) $data['Quantity'] : null,
                    'sales_order' => isset($data['SalesorderId']) ? (string) $data['SalesorderId'] : null,
                ];
            }
        }

        return null;
    }

    /**
     * Reads a DigiKey 2D label (ISO/IEC 15434 with ANSI MH10.8.2 data identifiers): P = DigiKey number,
     * 1P = manufacturer number, Q = quantity, 1K = sales order. Null when it is not such a code.
     *
     * @return array{digikey: ?string, manufacturer_reference: ?string, manufacturer: ?string, description: ?string, quantity: ?int, sales_order: ?string}|null
     */
    public static function parse2D(string $barcode): ?array
    {
        if (! str_starts_with($barcode, '[)>')) {
            return null;
        }

        $fields = [];

        // Fields are separated by GS (0x1D); scanners in keyboard mode sometimes send them as other characters.
        foreach (preg_split('/[\x1D\x1E\x04]/', $barcode) ?: [] as $field) {
            if (preg_match('/^(\d{0,2}[A-Z])(.+)$/', $field, $match)) {
                $fields[$match[1]] ??= trim($match[2]);
            }
        }

        if (! isset($fields['P']) && ! isset($fields['1P'])) {
            return null;
        }

        return [
            'digikey' => $fields['P'] ?? null,
            'manufacturer_reference' => $fields['1P'] ?? null,
            'manufacturer' => null,
            'description' => null,
            'quantity' => isset($fields['Q']) && ctype_digit($fields['Q']) ? (int) $fields['Q'] : null,
            'sales_order' => isset($fields['1K']) && ctype_digit($fields['1K']) ? $fields['1K'] : null,
        ];
    }

    /**
     * The DigiKey accounts of the connected user (Customer Resource); used to fill in the Account ID.
     *
     * @return list<array{id: string, company: ?string}>
     */
    public function accounts(): array
    {
        $data = $this->send('get', '/CustomerResource/v1/associatedaccounts', user: true)?->json() ?? [];

        return array_map(fn (array $account): array => ['id' => (string) $account['AccountId'], 'company' => $account['Address']['CompanyName'] ?? null], $data['Accounts'] ?? []);
    }

    /**
     * Where the user is sent to log in at DigiKey and allow the ERP (3-legged OAuth).
     */
    public function authorizeUrl(string $redirectUri, string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->distributor->api_username,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    /**
     * Exchanges the code DigiKey sent back for the user's tokens and keeps the refresh token.
     *
     * @throws DistributorException
     */
    public function connect(string $code, string $redirectUri): void
    {
        $this->storeTokens($this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]));
    }

    public function disconnect(): void
    {
        Cache::forget($this->cacheKey('user'));
        $this->distributor->forceFill(['api_refresh_token' => null, 'api_refresh_expires_at' => null])->save();
    }

    /**
     * Calls an API; null for a 404 when $notFoundIsEmpty.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws DistributorException
     */
    protected function send(string $method, string $path, array $query = [], bool $user = false, bool $notFoundIsEmpty = false): ?Response
    {
        $locale = in_array(app()->getLocale(), ['nl', 'fr'], true) ? app()->getLocale() : 'en';

        try {
            $response = Http::acceptJson()
                ->timeout(30)
                ->withToken($user ? $this->userToken() : $this->appToken())
                ->withHeaders(array_filter([
                    'X-DIGIKEY-Client-Id' => (string) $this->distributor->api_username,
                    'X-DIGIKEY-Locale-Site' => 'BE',
                    'X-DIGIKEY-Locale-Language' => $locale,
                    'X-DIGIKEY-Locale-Currency' => 'EUR',
                    'X-DIGIKEY-Account-Id' => filled($this->distributor->api_customer_number) ? trim((string) $this->distributor->api_customer_number) : null,
                ]))
                ->send(strtoupper($method), self::BASE.$path, $query === [] ? [] : ['query' => $query]);
        } catch (ConnectionException $exception) {
            throw new DistributorException(__('erp.stock.provider.service_unreachable', ['name' => 'DigiKey']), previous: $exception);
        }

        if ($response->status() === 404 && $notFoundIsEmpty) {
            return null;
        }

        if ($response->failed()) {
            $reason = trim((string) ($response->json('detail') ?: $response->json('ErrorMessage') ?: $response->json('title') ?: 'HTTP '.$response->status()));
            Log::warning('DigiKey API call failed', ['path' => $path, 'status' => $response->status(), 'reason' => $reason]);

            throw new DistributorException(in_array($response->status(), [401, 403], true)
                ? __('erp.stock.provider.login_failed', ['name' => 'DigiKey', 'reason' => $reason])
                : __('erp.stock.provider.call_failed', ['name' => 'DigiKey', 'reason' => $reason]));
        }

        return $response;
    }

    /**
     * The app's access token (client credentials), kept until shortly before it expires (10 minutes).
     */
    protected function appToken(): string
    {
        $this->ensureConfigured();

        return Cache::remember($this->cacheKey('app'), now()->addMinutes(8), fn (): string => (string) $this->token(['grant_type' => 'client_credentials'])['access_token']);
    }

    /**
     * The connected user's access token, renewed with the refresh token (which DigiKey replaces each time).
     */
    protected function userToken(): string
    {
        if (! $this->isConnected()) {
            throw new DistributorException(__('erp.distributors.digikey.not_connected'));
        }

        return Cache::remember($this->cacheKey('user'), now()->addMinutes(8), function (): string {
            try {
                $tokens = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $this->distributor->api_refresh_token]);
            } catch (DistributorException $exception) {
                // A refresh token that is no longer accepted: the user has to connect again.
                $this->distributor->forceFill(['api_refresh_token' => null, 'api_refresh_expires_at' => null])->save();

                throw new DistributorException(__('erp.distributors.digikey.not_connected'), previous: $exception);
            }

            $this->storeTokens($tokens);

            return (string) $tokens['access_token'];
        });
    }

    /**
     * @param  array<string, string>  $parameters
     * @return array<string, mixed>
     *
     * @throws DistributorException
     */
    protected function token(array $parameters): array
    {
        try {
            $response = Http::asForm()->acceptJson()->timeout(30)->post(self::TOKEN_URL, [
                'client_id' => (string) $this->distributor->api_username,
                'client_secret' => (string) $this->distributor->api_password,
                ...$parameters,
            ]);
        } catch (ConnectionException $exception) {
            throw new DistributorException(__('erp.stock.provider.service_unreachable', ['name' => 'DigiKey']), previous: $exception);
        }

        if ($response->failed() || blank($response->json('access_token'))) {
            $reason = trim((string) ($response->json('error_description') ?: $response->json('ErrorMessage') ?: $response->json('error') ?: 'HTTP '.$response->status()));
            Log::warning('DigiKey login refused', ['grant' => $parameters['grant_type'], 'status' => $response->status(), 'reason' => $reason]);

            throw new DistributorException(__('erp.stock.provider.login_failed', ['name' => 'DigiKey', 'reason' => $reason]));
        }

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    protected function storeTokens(array $tokens): void
    {
        if (filled($tokens['refresh_token'] ?? null)) {
            $this->distributor->forceFill([
                'api_refresh_token' => (string) $tokens['refresh_token'],
                'api_refresh_expires_at' => now()->addSeconds((int) ($tokens['refresh_token_expires_in'] ?? 90 * 86400)),
            ])->save();
        }

        Cache::put($this->cacheKey('user'), (string) $tokens['access_token'], now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 600) - 60)));
    }

    protected function cacheKey(string $kind): string
    {
        return "digikey-token:{$kind}:{$this->distributor->id}:".md5((string) $this->distributor->api_username);
    }

    protected function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new DistributorException(__('erp.stock.provider.not_configured', ['name' => 'DigiKey']));
        }
    }

    protected function ensureOrders(): void
    {
        $this->ensureConfigured();

        if (blank($this->distributor->api_customer_number)) {
            throw new DistributorException(__('erp.distributors.digikey.no_account'));
        }
    }

    protected static function date(mixed $value): ?CarbonInterface
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
