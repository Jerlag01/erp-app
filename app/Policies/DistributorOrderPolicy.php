<?php

namespace App\Policies;

use App\Models\DistributorOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The orders at the distributors follow the "distributor_orders" permissions; orders that came from a
 * distributor's API are not changed or removed by hand (the next sync would undo it).
 */
class DistributorOrderPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'distributor_orders';
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $model instanceof DistributorOrder && ! $model->isFromApi();
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $model instanceof DistributorOrder && ! $model->isFromApi();
    }
}
