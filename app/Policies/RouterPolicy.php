<?php

namespace App\Policies;

use App\Models\User;

/**
 * 4G routers follow the "routers" permissions. Login credentials and VPN files need "routers.secrets",
 * which administrators only have when it is ticked on their role.
 */
class RouterPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'routers';
    }

    public function viewSecrets(User $user): bool
    {
        return $this->allows($user, 'view') && $this->allows($user, 'secrets');
    }
}
