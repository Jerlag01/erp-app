<?php

namespace App\Policies;

use App\Models\BoardSupplier;
use App\Models\User;

/**
 * The electrical board suppliers are managed with "board_deliveries.manage_suppliers".
 */
class BoardSupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return modules()->boardDeliveries() && $user->hasPermission('board_deliveries.manage_suppliers');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, BoardSupplier $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, BoardSupplier $supplier): bool
    {
        return $this->viewAny($user) && ! $supplier->followUps()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
