<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Projects follow the "projects" permissions, but only for projects the user can see: the ones
 * they lead or one of their teams is assigned to (administrators see all of them).
 * Project leaders may do everything on their own projects except deleting them.
 * Offers and invoices need "project_finance" (or being the project leader): teams don't see them.
 */
class ProjectPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'projects';
    }

    public function view(User $user, Model $project): bool
    {
        return $this->allows($user, 'view') && $project->isVisibleTo($user);
    }

    public function update(User $user, Model $project): bool
    {
        return $this->view($user, $project) && ($this->allows($user, 'update') || $project->isLedBy($user));
    }

    public function delete(User $user, Model $project): bool
    {
        return $this->view($user, $project) && $this->allows($user, 'delete');
    }

    public function manageFiles(User $user, Project $project): bool
    {
        return $this->view($user, $project) && ($this->allows($user, 'files') || $project->isLedBy($user));
    }

    public function manageParts(User $user, Project $project): bool
    {
        return $this->view($user, $project) && ($this->allows($user, 'parts') || $project->isLedBy($user));
    }

    public function viewFinance(User $user, Project $project): bool
    {
        return $this->view($user, $project) && ($user->isAdmin() || $project->isLedBy($user) || $user->hasPermission('project_finance.view') || $user->hasPermission('project_finance.manage'));
    }

    public function manageFinance(User $user, Project $project): bool
    {
        return $this->view($user, $project) && ($user->isAdmin() || $project->isLedBy($user) || $user->hasPermission('project_finance.manage'));
    }
}
