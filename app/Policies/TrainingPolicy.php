<?php

namespace App\Policies;

use App\Models\Training;
use App\Models\User;
use App\Support\Planning;

/**
 * Trainings are planned by administrators and "planning.trainings"; participants see their own.
 */
class TrainingPolicy
{
    public function __construct(protected Planning $planning) {}

    public function viewAny(User $user): bool
    {
        return $this->planning->canPlanTrainings($user);
    }

    public function view(User $user, Training $training): bool
    {
        return $this->planning->canPlanTrainings($user) || ($this->planning->enabled() && $training->participants()->whereKey($user->id)->exists());
    }

    public function create(User $user): bool
    {
        return $this->planning->canPlanTrainings($user);
    }

    public function update(User $user, Training $training): bool
    {
        return $this->planning->canPlanTrainings($user);
    }

    public function delete(User $user, Training $training): bool
    {
        return $this->planning->canPlanTrainings($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->planning->canPlanTrainings($user);
    }
}
