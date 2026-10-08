<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Maintenance belongs to the asset: viewing it needs asset view, recording it needs asset update.
 */
class ItMaintenancePolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'it_assets';
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, 'update');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'update');
    }
}
