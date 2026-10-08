<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\User;

/**
 * Absences (medical, overtime as leave, family leave) are entered by administrators only.
 * Employees see their own on "My absences" and may upload a missing certificate there.
 */
class AbsencePolicy
{
    protected function admin(User $user): bool
    {
        return modules()->vacations() && $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function view(User $user, Absence $absence): bool
    {
        return modules()->vacations() && ($user->isAdmin() || $absence->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $this->admin($user);
    }

    public function update(User $user, Absence $absence): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, Absence $absence): bool
    {
        return $this->admin($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->admin($user);
    }

    /**
     * The employee uploads their own certificate while the administrator has not uploaded one.
     */
    public function uploadDocument(User $user, Absence $absence): bool
    {
        return modules()->vacations() && $user->is_active && $absence->user_id === $user->id && $absence->employeeMayUpload();
    }
}
