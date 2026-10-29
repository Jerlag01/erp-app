<?php

namespace App\Policies;

use App\Models\FuelPass;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Fuel passes follow "fuel_passes" (fleet module); the members of a vehicle's team also see the
 * passes of that vehicle (and their PIN), without the permission.
 */
class FuelPassPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'fuel_passes';
    }

    public function viewAny(User $user): bool
    {
        return parent::viewAny($user) || (modules()->fleet() && FuelPass::query()->visibleTo($user)->exists());
    }

    public function view(User $user, Model $model): bool
    {
        return modules()->fleet() && FuelPass::query()->visibleTo($user)->whereKey($model->getKey())->exists();
    }
}
