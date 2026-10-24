<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * BAE orders need "batteries.bae_orders" (they show purchase prices), for 604 projects the user can see.
 * New 604 projects get theirs automatically; deleting one also needs "batteries.delete".
 */
class BaeOrderPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'batteries';
    }

    public function viewAny(User $user): bool
    {
        return modules()->batteries() && $this->allows($user, 'bae_orders');
    }

    public function view(User $user, Model $order): bool
    {
        return $this->viewAny($user) && $order->project->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $order): bool
    {
        return $this->view($user, $order);
    }

    public function delete(User $user, Model $order): bool
    {
        return $this->view($user, $order) && $this->allows($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user) && $this->allows($user, 'delete');
    }
}
