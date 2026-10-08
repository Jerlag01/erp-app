<?php

namespace App\Policies;

use App\Models\ProjectCategory;
use App\Models\User;

/**
 * Project categories are managed by administrators only. A category that holds projects
 * cannot be deleted.
 */
class ProjectCategoryPolicy
{
    protected function allowed(User $user): bool
    {
        return modules()->projects() && $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, ProjectCategory $category): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, ProjectCategory $category): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, ProjectCategory $category): bool
    {
        return $this->allowed($user) && ! $category->hasProjects();
    }

    public function deleteAny(User $user): bool
    {
        return $this->allowed($user);
    }
}
