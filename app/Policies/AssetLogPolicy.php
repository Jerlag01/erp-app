<?php

namespace App\Policies;

class AssetLogPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'asset_logs';
    }
}
