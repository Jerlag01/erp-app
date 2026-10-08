<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Roles are managed by administrators only.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Role $role): bool
    {
        return $user->isAdmin();
    }

    /**
     * A role that still has users cannot be deleted, nor can the user's own role or the guest role.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->isAdmin()
            && ! $role->is_guest
            && $user->role_id !== $role->id
            && ! $role->users()->exists();
    }
}
