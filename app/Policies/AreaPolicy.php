<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps Filament's abilities onto the role permissions of one area, e.g.
 * "vehicles.view", "vehicles.update". Nothing is allowed while the area's
 * module is switched off, not even for administrators.
 */
abstract class AreaPolicy
{
    abstract protected function area(): string;

    protected function allows(User $user, string $ability): bool
    {
        $module = Permissions::moduleOf($this->area());

        return ($module === null || modules()->enabled($module))
            && $user->hasPermission($this->area().'.'.$ability);
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }
}
