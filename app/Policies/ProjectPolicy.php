<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Projects follow the "projects" permissions, but only for projects the user can see: the ones
 * they lead or one of their teams is assigned to (administrators see all of them).
 * Project leaders may do everything on their own projects except deleting them.
 * Employees planned on a project may view it. Offers and invoices need "project_finance" (or being the project leader): teams don't see them.
 */
class ProjectPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'projects';
    }

    /**
     * Employees in the planning for a project may open it from their planning, even without
     * "projects.view" (offers and invoices still need the finance rights below).
     */
    public function view(User $user, Model $project): bool
    {
        if (! modules()->projects()) {
            return false;
        }

        return ($this->allows($user, 'view') && $project->isVisibleTo($user)) || $project->isPlannedFor($user);
    }

    /**
     * Archived (closed) projects are read-only, except for administrators.
     */
    protected function editable(User $user, Model $project): bool
    {
        return ! $project->isArchived() || $user->isAdmin();
    }

    public function update(User $user, Model $project): bool
    {
        return $this->view($user, $project) && $this->editable($user, $project) && ($this->allows($user, 'update') || $project->isLedBy($user));
    }

    public function delete(User $user, Model $project): bool
    {
        return $this->view($user, $project) && $this->editable($user, $project) && $this->allows($user, 'delete');
    }

    public function manageFiles(User $user, Project $project): bool
    {
        return $this->view($user, $project) && $this->editable($user, $project) && ($this->allows($user, 'files') || $project->isLedBy($user));
    }

    public function manageParts(User $user, Project $project): bool
    {
        return $this->view($user, $project) && $this->editable($user, $project) && ($this->allows($user, 'parts') || $project->isLedBy($user));
    }

    /**
     * Start a new project from this one (also from an archived one): "projects.copy".
     */
    public function copy(User $user, Project $project): bool
    {
        return $this->view($user, $project) && $this->allows($user, 'copy') && $this->allows($user, 'create');
    }

    /**
     * Add a GPW request by hand (asked through a personal mail) or record the decision on one:
     * "projects.gpw", on a project that is not closed.
     */
    public function manageGpw(User $user, Project $project): bool
    {
        return $this->view($user, $project) && ! $project->isArchived() && $this->allows($user, 'gpw');
    }

    /**
     * Ask the client for a GPW: "projects.gpw", only while the project is to be executed.
     */
    public function requestGpw(User $user, Project $project): bool
    {
        return $this->view($user, $project)
            && ! $project->isArchived()
            && $this->allows($user, 'gpw')
            && $project->status === ProjectStatus::ToBeExecuted;
    }

    /**
     * The ZIP of a closed project.
     */
    public function downloadArchive(User $user, Project $project): bool
    {
        return $project->isArchived() && filled($project->archive_path) && $this->view($user, $project) && $user->hasPermission('projects.download_pdf');
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
