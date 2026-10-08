<?php

namespace App\Policies;

class ItLicensePolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'it_licenses';
    }
}
