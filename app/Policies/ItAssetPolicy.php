<?php

namespace App\Policies;

class ItAssetPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'it_assets';
    }
}
