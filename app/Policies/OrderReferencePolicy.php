<?php

namespace App\Policies;

use App\Models\OrderReference;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Viewing, taking (+1), editing details and withdrawing follow the "order_references" permissions.
 * Only the latest reference of a year can be withdrawn, so the sequence stays without gaps.
 */
class OrderReferencePolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'order_references';
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $model instanceof OrderReference && $model->isLatestOfYear();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
