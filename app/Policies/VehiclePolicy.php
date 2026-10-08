<?php

namespace App\Policies;

class VehiclePolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'vehicles';
    }
}
