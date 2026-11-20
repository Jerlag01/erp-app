<?php

namespace App\Policies;

use App\Models\PlanningItem;
use App\Models\User;
use App\Support\Planning;

/**
 * Planning rights are described in App\Support\Planning.
 */
class PlanningItemPolicy
{
    public function __construct(protected Planning $planning) {}

    public function viewAny(User $user): bool
    {
        return $this->planning->enabled();
    }

    public function view(User $user, PlanningItem $item): bool
    {
        // A draft is only for those who plan, never for the employee.
        if ($item->is_draft && ! $this->planning->seesDrafts($user)) {
            return false;
        }

        return $this->planning->enabled() && (
            $this->planning->canSeePlanningOf($user, $item->user)
            || ($item->project !== null && $item->project->isLedBy($user))
            || $this->planning->canManage($user, $item)
        );
    }

    public function create(User $user): bool
    {
        return $this->planning->canPlan($user);
    }

    public function update(User $user, PlanningItem $item): bool
    {
        return $this->planning->canManage($user, $item);
    }

    public function delete(User $user, PlanningItem $item): bool
    {
        return $this->planning->canManage($user, $item);
    }

    public function confirm(User $user, PlanningItem $item): bool
    {
        return $this->planning->canConfirmItem($user, $item);
    }

    public function tickTasks(User $user, PlanningItem $item): bool
    {
        return $this->view($user, $item) && $this->planning->canTickTasks($user, $item);
    }
}
