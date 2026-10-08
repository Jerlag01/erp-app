<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

/**
 * Every employee may report an incident; the submissions are handled by administrators.
 * Reporters can always see their own reports.
 */
class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return modules()->incidents() && $user->isAdmin();
    }

    public function view(User $user, Incident $incident): bool
    {
        return modules()->incidents() && ($user->isAdmin() || $incident->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return modules()->incidents() && $user->is_active;
    }

    public function update(User $user, Incident $incident): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
