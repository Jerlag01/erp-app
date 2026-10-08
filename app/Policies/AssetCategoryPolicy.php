<?php

namespace App\Policies;

use App\Models\AssetCategory;
use App\Models\User;

/**
 * Asset categories are reference data, managed by administrators only.
 */
class AssetCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && modules()->assets();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AssetCategory $category): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, AssetCategory $category): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
