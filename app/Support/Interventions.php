<?php

namespace App\Support;

use App\Enums\InterventionReportStatus;
use App\Enums\MaterialDirection;
use App\Enums\ProjectNumbering;
use App\Models\InterventionCategory;
use App\Models\InterventionReport;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkSite;
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

        if ($user->isAdmin() || ($canViewProjects && ! $user->seesOnlyOwnTeams('projects'))) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->whereRaw('1 = 0')
            ->when($canViewProjects, fn (Builder $query) => $query
                ->orWhereHas('leaders', fn (Builder $query) => $query->whereKey($user->id))
                ->orWhereHas('teams', fn (Builder $query) => $query->whereKey($user->teamIds() ?: [0])))
            ->when(modules()->planning(), fn (Builder $query) => $query->orWhereHas('planningItems', fn (Builder $query) => $query->where('user_id', $user->id))));
    }

    /**
     * Closing: anyone who sees the intervention, while one of its COW codes (or the given one) is not closed
     * yet or was rejected.
     */
    public function canClose(User $user, Project $project, ?int $workSiteId = null): bool
    {
        if (! $this->appliesTo($project) || ! $user->is_active || ! Gate::forUser($user)->allows('view', $project)) {
            return false;
        }

        return $workSiteId !== null
            ? $this->isOpen($this->site($project, $workSiteId))
            : $this->openSites($project) !== [];
    }

    /**
     * The COW codes of an intervention, each with its report: the project's own work site first, then the
     * extra COW codes (one WO/PO on several COW codes). The first one's report may be on another COW code
     * than the project's (corrected when closing), as long as that is not one of the extra ones.
     *
     * @return list<array{work_site_id: ?int, work_site: ?WorkSite, report: ?InterventionReport}>
     */
    public function sites(Project $project): array
    {
        $reports = $project->interventionReports;
        $extras = $project->interventionWorkSites->reject(fn (WorkSite $site): bool => $site->id === $project->work_site_id)->values();
        $extraIds = $extras->modelKeys();
        $reportOn = fn (?int $siteId): ?InterventionReport => $reports->first(fn (InterventionReport $report): bool => $siteId !== null && (int) $report->work_site_id === $siteId);
        $main = $reportOn($project->work_site_id) ?? $reports->first(fn (InterventionReport $report): bool => ! in_array((int) $report->work_site_id, $extraIds, true));

        return [
            ['work_site_id' => $main->work_site_id ?? $project->work_site_id, 'work_site' => $main?->workSite ?? $project->workSite, 'report' => $main],
            ...$extras->map(fn (WorkSite $site): array => ['work_site_id' => $site->id, 'work_site' => $site, 'report' => $reportOn($site->id)])->all(),
        ];
    }

    /**
     * The COW code a report is (or will be) made for: one of the extra COW codes, else the project's own.
     *
     * @return array{work_site_id: ?int, work_site: ?WorkSite, report: ?InterventionReport}
     */
    public function site(Project $project, ?int $workSiteId): array
    {
        $sites = $this->sites($project);

        foreach (array_slice($sites, 1) as $site) {
            if ($workSiteId !== null && $site['work_site_id'] === $workSiteId) {
                return $site;
            }
        }

        return $sites[0];
    }

    /**
     * @param  array{report: ?InterventionReport}  $site
     */
    public function isOpen(array $site): bool
    {
        return $site['report'] === null || $site['report']->status === InterventionReportStatus::Rejected;
    }

    /**
     * The COW codes still to close (or closed again after a rejection).
     *
     * @return list<array{work_site_id: ?int, work_site: ?WorkSite, report: ?InterventionReport}>
     */
    public function openSites(Project $project): array
    {
        return array_values(array_filter($this->sites($project), fn (array $site): bool => $this->isOpen($site)));
    }

    public function allClosed(Project $project): bool
    {
        return $this->openSites($project) === [];
    }

    public function allApproved(Project $project): bool
    {
        return collect($this->sites($project))->every(fn (array $site): bool => $site['report']?->status === InterventionReportStatus::Approved);
    }

    public function hasSeveralSites(Project $project): bool
    {
        return count($this->sites($project)) > 1;
    }

    /**
     * "02NO1 · Zaventem".
     */
    public static function siteLabel(?WorkSite $site, ?string $fallback = null): string
    {
        return $site ? trim($site->cow_code.' · '.$site->city, ' ·') : ($fallback ?? '—');
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
