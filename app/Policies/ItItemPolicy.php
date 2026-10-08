<?php

namespace App\Policies;

class ItItemPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'it_items';
    }
}
