<?php

namespace App\Policies;

/**
 * Stock master data: manufacturers, distributors, contacts, categories and units.
 */
class DistributorContactPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'stock_master_data';
    }
}
