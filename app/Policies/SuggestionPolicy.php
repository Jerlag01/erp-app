<?php

namespace App\Policies;

use App\Models\Suggestion;
use App\Models\User;

/**
 * Every employee may submit to the idea/complaint box; the entries are read by administrators.
 */
class SuggestionPolicy
{
    public function viewAny(User $user): bool
    {
        return modules()->suggestions() && $user->isAdmin();
    }

    public function view(User $user, Suggestion $suggestion): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return modules()->suggestions() && $user->is_active;
    }

    public function update(User $user, Suggestion $suggestion): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Suggestion $suggestion): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
