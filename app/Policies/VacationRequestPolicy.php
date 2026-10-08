<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VacationRequest;

/**
 * Covers the approval list. Requesting your own vacation needs no permission
 * (see the "My vacation" resource); viewing and approving others' requests does.
 */
class VacationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return modules()->vacations()
            && ($user->hasPermission('vacations.view') || $user->hasPermission('vacations.approve'));
    }

    public function view(User $user, VacationRequest $request): bool
    {
        return $this->viewAny($user) || $request->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return modules()->vacations() && $user->isAdmin();
    }

    public function update(User $user, VacationRequest $request): bool
    {
        return modules()->vacations() && $user->isAdmin();
    }

    /**
     * Approvers decide on others' requests; only administrators may also decide on their own.
     */
    public function approve(User $user, VacationRequest $request): bool
    {
        return modules()->vacations()
            && $request->isPending()
            && $user->hasPermission('vacations.approve')
            && ($request->user_id !== $user->id || $user->isAdmin());
    }

    public function delete(User $user, VacationRequest $request): bool
    {
        return modules()->vacations() && $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return modules()->vacations() && $user->isAdmin();
    }
}
