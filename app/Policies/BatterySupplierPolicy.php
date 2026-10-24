<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Battery suppliers are managed with "batteries.suppliers".
 */
class BatterySupplierPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'batteries';
    }

    public function viewAny(User $user): bool
    {
        return modules()->batteries() && $this->allows($user, 'suppliers');
    }

    public function view(User $user, Model $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Model $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
