<?php

namespace App\Policies;

/**
 * BTV controls ("btv_controls", module projects): on the projects the user can see.
 */
class BtvControlPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'btv_controls';
    }
}
