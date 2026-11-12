<?php

namespace App\Services\Distributors;

use Carbon\CarbonInterface;

/**
 * A distributor connection that also gives the orders placed with it (Purchasing > Orders). Implemented
 * per distributor once its API documentation for orders is known.
 */
interface DistributorOrderSource
{
    /**
     * Whether this connection can give orders yet.
     */
    public function supportsOrders(): bool;

    /**
     * The orders placed or changed since this moment (all recent ones when null).
     *
     * @return iterable<OrderInfo>
     *
     * @throws DistributorException
     */
    public function orders(?CarbonInterface $since = null): iterable;
}
