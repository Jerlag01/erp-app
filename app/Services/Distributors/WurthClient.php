<?php

namespace App\Services\Distributors;

use App\Models\Distributor;
use App\Models\StockItem;
use Carbon\CarbonInterface;

/**
 * Würth (Belgium): prepared for its API. The distributor carries the customer number, user and password
 * (Stock > Distributors, price provider "Würth"); the calls themselves follow once Würth's API
 * documentation (catalogue: prices and articles; orders: order history and delivery status) is in.
 * Until then lookups and orders report that the connection is not available yet.
 */
class WurthClient implements DistributorClient, DistributorOrderSource
{
    public function __construct(protected Distributor $distributor) {}

    public function isConfigured(): bool
    {
        return filled($this->distributor->api_username)
            && filled($this->distributor->api_password)
            && filled($this->distributor->api_customer_number);
    }

    public function lookup(StockItem $item, ?string $reference = null): ?ProductInfo
    {
        $this->ensureReady();

        throw new DistributorException(__('erp.stock.provider.not_available', ['name' => 'Würth']));
    }

    public function supportsOrders(): bool
    {
        return false;
    }

    public function orders(?CarbonInterface $since = null): iterable
    {
        $this->ensureReady();

        throw new DistributorException(__('erp.stock.provider.not_available', ['name' => 'Würth']));
    }

    protected function ensureReady(): void
    {
        if (! $this->isConfigured()) {
            throw new DistributorException(__('erp.stock.provider.not_configured', ['name' => 'Würth']));
        }
    }
}
