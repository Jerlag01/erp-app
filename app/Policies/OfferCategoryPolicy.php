<?php

namespace App\Policies;

/**
 * The offer categories and default offer lines follow "finance_master_data".
 */
class OfferCategoryPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'finance_master_data';
    }
}
