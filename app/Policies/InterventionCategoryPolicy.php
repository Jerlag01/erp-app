<?php

namespace App\Policies;

use App\Models\InterventionCategory;
use App\Models\User;

/**
 * Master data of the interventions module, managed by administrators.
 */
class InterventionCategoryPolicy
{
    protected function admin(User $user): bool
    {
        return modules()->interventions() && $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function view(User $user, InterventionCategory $record): bool
    {
        return $this->admin($user);
    }

    public function create(User $user): bool
    {
        return $this->admin($user);
    }

    public function update(User $user, InterventionCategory $record): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, InterventionCategory $record): bool
    {
        return $this->admin($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->admin($user);
    }
}
