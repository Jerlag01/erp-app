<?php

namespace App\Policies;

/**
 * The badge & key list and the security tag list follow the "access_control" permissions (employee
 * module); entries are only removed with "access_control.delete".
 */
class AccessBadgePolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'access_control';
    }
}
