<?php

namespace App\Services\Distributors;

use App\Models\StockItem;

/**
 * A connection to a distributor's catalogue (prices, images).
 */
interface DistributorClient
{
    /**
     * Whether the credentials and endpoint needed to call the catalogue are present.
     */
    public function isConfigured(): bool;

    /**
     * Looks the item up by distributor reference, or else by manufacturer reference.
     * Returns null when the catalogue does not know the article.
     *
     * @throws DistributorException when the call fails or the connection is not set up
     */
    public function lookup(StockItem $item): ?ProductInfo;
}
