<?php

namespace App\Support;

use App\Enums\InterventionReportStatus;
use App\Enums\MaterialDirection;
use App\Models\InterventionCategory;
use App\Models\Project;
use App\Models\User;
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
