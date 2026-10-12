<?php

namespace App\Policies;

use App\Models\User;

/**
 * Charging stations and 4G routers follow the "charging" permissions. Passwords, recovery codes and
 * VPN files need "charging.secrets".
 */
class ChargingStationPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'charging';
    }

    public function viewSecrets(User $user): bool
    {
        return $this->allows($user, 'view') && $this->allows($user, 'secrets');
    }
}
