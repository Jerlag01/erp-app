<?php

namespace App\Services\Distributors;

/**
 * A distributor connection that can hand over the webshop basket (Rexel GetBasket, Cebeo's open order):
 * the order is put together in the webshop and its lines are imported into Purchasing > Orders.
 */
interface DistributorBasketSource
{
    /**
     * The lines in the webshop basket; null when the basket is empty.
     *
     * @throws DistributorException
     */
    public function basket(): ?OrderInfo;

    /**
     * Whether fetching the basket empties it in the webshop (Rexel does, unless agreed otherwise).
     */
    public function basketIsEmptied(): bool;
}
