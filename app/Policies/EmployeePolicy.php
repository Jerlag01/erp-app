<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * The employee register holds sensitive personal data (national number, bank account,
 * medical follow-up), so it is for administrators only.
 */
class EmployeePolicy
{
    protected function allowed(User $user): bool
    {
        return modules()->employees() && $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->allowed($user);
    }

    /**
     * Employees are not deleted (their history must stay); they are marked as having left.
     * Only records of people who have left can be removed, e.g. to honour a GDPR erasure request.
     */
    public function delete(User $user, Employee $employee): bool
    {
        return $this->allowed($user) && ! $employee->isEmployed();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
