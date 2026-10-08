<?php

namespace App\Policies;

use App\Models\Holiday;
use App\Models\User;

/**
 * Public holidays are set by administrators only.
 */
class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && modules()->vacations();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
