<?php

namespace App\Policies;

class LocationPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'locations';
    }
}
