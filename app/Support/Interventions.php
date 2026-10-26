<?php

namespace App\Support;

use App\Enums\InterventionReportStatus;
use App\Enums\MaterialDirection;
use App\Enums\ProjectNumbering;
use App\Models\InterventionCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The interventions module: projects in an intervention category (600) get intervention
 * categories, a closing report by the employee and a review by an administrator.
 * Without the module, category 600 works as an ordinary project category.
 *
 * Closing: anyone who can see the intervention (team, project leader, planned on it) closes it
 * while it is not closed yet or after a rejection. Reviewing (approve with PO number / reject)
 * and the master data are for administrators.
 */
class Interventions
{
    public function enabled(): bool
    {
        return modules()->interventions();
    }

    public function appliesTo(Project $project): bool
    {
        return $this->enabled() && $project->isIntervention();
    }

    /**
     * The interventions a user sees: all for administrators and those who see every project; else
     * the ones they lead, of their teams (with project rights) or that they are planned on.
     *
     * @return Builder<Project>
     */
    public function visibleTo(?User $user): Builder
    {
        $query = Project::query()->whereHas('category', fn (Builder $query) => $query->where('numbering', ProjectNumbering::Intervention->value));

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $canViewProjects = $user->hasPermission('projects.view');

        if ($user->isAdmin() || ($canViewProjects && ! $user->seesOnlyOwnTeams())) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->whereRaw('1 = 0')
            ->when($canViewProjects, fn (Builder $query) => $query
                ->orWhereHas('leaders', fn (Builder $query) => $query->whereKey($user->id))
                ->orWhereHas('teams', fn (Builder $query) => $query->whereKey($user->teamIds() ?: [0])))
            ->when(modules()->planning(), fn (Builder $query) => $query->orWhereHas('planningItems', fn (Builder $query) => $query->where('user_id', $user->id))));
    }

    public function canClose(User $user, Project $project): bool
    {
        if (! $this->appliesTo($project) || ! $user->is_active || ! Gate::forUser($user)->allows('view', $project)) {
            return false;
        }

        $report = $project->interventionReport;

        return $report === null || $report->status === InterventionReportStatus::Rejected;
    }

    public function canReview(User $user): bool
    {
        return $this->enabled() && $user->isAdmin();
    }

    /**
     * Who may open the PDF: administrators, and those who see the project's offers and invoices.
     */
    public function canDownloadPdf(User $user, Project $project): bool
    {
        return $this->enabled() && ($user->isAdmin() || Gate::forUser($user)->allows('viewFinance', $project));
    }

    /**
     * Material is "freed" only with a category such as DECONNECT and "used" with all the others.
     *
     * @param  Collection<int, InterventionCategory>  $categories
     * @return list<MaterialDirection>
     */
    public function directions(Collection $categories): array
    {
        $frees = $categories->contains(fn (InterventionCategory $category): bool => $category->frees_material);
        $uses = $categories->isEmpty() || $categories->contains(fn (InterventionCategory $category): bool => ! $category->frees_material);

        return array_values(array_filter([$uses ? MaterialDirection::Used : null, $frees ? MaterialDirection::Freed : null]));
    }
}
