<?php

namespace App\Policies;

use App\Models\EmployeeCertificate;
use App\Models\User;

/**
 * The certificates (trainings) of the employees (employee register and the overview "Trainings & medical check-ups"):
 * "certificates.view/create/update/delete"; administrators have them all.
 */
class EmployeeCertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return modules()->employees() && $user->hasPermission('certificates.view');
    }

    public function view(User $user, EmployeeCertificate $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->hasPermission('certificates.create');
    }

    public function update(User $user, EmployeeCertificate $record): bool
    {
        return $this->viewAny($user) && $user->hasPermission('certificates.update');
    }

    public function delete(User $user, EmployeeCertificate $record): bool
    {
        return $this->viewAny($user) && $user->hasPermission('certificates.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user) && $user->hasPermission('certificates.delete');
    }
}
