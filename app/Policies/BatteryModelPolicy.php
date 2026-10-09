<?php

namespace App\Policies;

/**
 * Battery master data (battery models with their racks, Frötek cables).
 */
class BatteryModelPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'battery_master_data';
    }
}
