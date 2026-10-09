<?php

namespace App\Policies;

use App\Models\InterventionReport;
use App\Models\User;
use App\Support\Interventions;

/**
 * Closed interventions are reviewed by administrators. Reports are made by closing an
 * intervention, never by hand, and they are not deleted (they remain the basis for invoicing).
 */
class InterventionReportPolicy
{
    public function __construct(protected Interventions $interventions) {}

    public function viewAny(User $user): bool
    {
        return $this->interventions->canReview($user);
    }

    public function view(User $user, InterventionReport $report): bool
    {
        return $this->interventions->canReview($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, InterventionReport $report): bool
    {
        return false;
    }

    public function review(User $user, InterventionReport $report): bool
    {
        return $this->interventions->canReview($user) && $report->isPending();
    }

    public function delete(User $user, InterventionReport $report): bool
    {
        return false;
    }
}
