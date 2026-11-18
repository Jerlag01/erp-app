<?php

namespace App\Policies;

use App\Models\EmployeeMedicalCheck;
use App\Models\User;

/**
 * The medical check-ups of the employees (employee register and the overview "Trainings & medical check-ups"):
 * "medical_checks.view/create/update/delete"; administrators have them all.
 */
class EmployeeMedicalCheckPolicy
{
    public function viewAny(User $user): bool
    {
        return modules()->employees() && $user->hasPermission('medical_checks.view');
    }

    public function view(User $user, EmployeeMedicalCheck $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->hasPermission('medical_checks.create');
    }

    public function update(User $user, EmployeeMedicalCheck $record): bool
    {
        return $this->viewAny($user) && $user->hasPermission('medical_checks.update');
    }

    public function delete(User $user, EmployeeMedicalCheck $record): bool
    {
        return $this->viewAny($user) && $user->hasPermission('medical_checks.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user) && $user->hasPermission('medical_checks.delete');
    }
}
