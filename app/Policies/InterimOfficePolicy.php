<?php

namespace App\Policies;

use App\Models\InterimOffice;
use App\Models\User;

/**
 * Interim offices and their contacts are managed by administrators, with the employee register.
 */
class InterimOfficePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && modules()->employees();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, InterimOffice $office): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, InterimOffice $office): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
