<?php

namespace App\Services\Distributors;

use App\Models\Distributor;
use App\Models\StockItem;

/**
 * Base for distributors whose API is not connected yet. Cebeo and Rexel only give API
 * access (and its documentation) to customers, so the actual requests are added once the
 * credentials and specification are known. Until then a lookup explains what is missing
 * instead of guessing at endpoints.
 */
abstract class PendingApiClient implements DistributorClient
{
    public function __construct(protected Distributor $distributor) {}

    abstract protected function name(): string;

    /**
     * The API base URL from config/services.php, e.g. services.cebeo.url.
     */
    abstract protected function baseUrl(): ?string;

    public function isConfigured(): bool
    {
        return filled($this->baseUrl())
            && filled($this->distributor->api_username)
            && filled($this->distributor->api_password);
    }

    public function lookup(StockItem $item): ?ProductInfo
    {
        if (! $this->isConfigured()) {
            throw new DistributorException(__('erp.stock.provider.not_configured', ['name' => $this->name()]));
        }

        throw new DistributorException(__('erp.stock.provider.not_implemented', ['name' => $this->name()]));
    }
}
