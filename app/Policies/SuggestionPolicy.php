<?php

namespace App\Policies;

use App\Models\Suggestion;
use App\Models\User;

/**
 * Every employee may submit to the idea/complaint box; the entries are read by administrators.
 * Administrators and the submitter reply to each other (respond).
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

    /**
     * Taking part in the conversation (reading the replies and the photos, replying): administrators,
     * and the employee who submitted it.
     */
    public function respond(User $user, Suggestion $suggestion): bool
    {
        return modules()->suggestions() && $user->is_active && ($user->isAdmin() || $suggestion->user_id === $user->id);
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
