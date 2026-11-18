<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

/**
 * Every employee may report an incident and always sees their own reports. Everyone's reports: "incidents.view";
 * handling them (status, follow-up) "incidents.update", removing them "incidents.delete" (administrators have all).
 */
class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return modules()->incidents() && $user->hasPermission('incidents.view');
    }

    public function view(User $user, Incident $incident): bool
    {
        return modules()->incidents() && ($user->hasPermission('incidents.view') || $incident->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return modules()->incidents() && $user->is_active;
    }

    public function update(User $user, Incident $incident): bool
    {
        return $this->viewAny($user) && $user->hasPermission('incidents.update');
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $this->viewAny($user) && $user->hasPermission('incidents.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user) && $user->hasPermission('incidents.delete');
    }
}
