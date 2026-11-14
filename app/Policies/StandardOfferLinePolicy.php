<?php

namespace App\Policies;

/**
 * The offer categories and default offer lines follow "offer_master_data" (module Offers).
 */
class StandardOfferLinePolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'offer_master_data';
    }
}
