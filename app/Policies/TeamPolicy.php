<?php

namespace App\Policies;

class TeamPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'teams';
    }
}
