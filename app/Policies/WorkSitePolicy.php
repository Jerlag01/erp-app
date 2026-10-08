<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSite;

/**
 * Work sites. Changing the building type (with its log) and the free remarks have their own
 * abilities, so someone can e.g. add remarks without being able to edit the address.
 */
class WorkSitePolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'work_sites';
    }

    public function changeType(User $user, WorkSite $site): bool
    {
        return $this->allows($user, 'change_type');
    }

    public function remark(User $user, WorkSite $site): bool
    {
        return $this->allows($user, 'remarks');
    }
}
