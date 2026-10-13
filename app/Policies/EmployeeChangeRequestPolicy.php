<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Changes and certificates employees submit are handled by administrators only.
 */
class EmployeeChangeRequestPolicy
{
    protected function allowed(User $user): bool
    {
        return modules()->employees() && $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, Model $request): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, Model $request): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, Model $request): bool
    {
        return $this->allowed($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allowed($user);
    }
}
