<?php

namespace App\Services\Distributors;

/**
 * A distributor connection that gives one order by the distributor's order number (Cebeo): used to keep
 * the open orders in Purchasing > Orders up to date (delivered quantities, status, expected delivery).
 */
interface DistributorOrderLookup
{
    /**
     * The order with this number; null when the distributor does not know it.
     *
     * @throws DistributorException
     */
    public function order(string $number): ?OrderInfo;
}
