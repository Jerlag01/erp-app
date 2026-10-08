<?php

namespace App\Policies;

class AssetPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'assets';
    }
}
