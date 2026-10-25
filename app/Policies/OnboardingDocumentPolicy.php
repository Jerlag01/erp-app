<?php

namespace App\Policies;

use App\Models\OnboardingDocument;
use App\Models\User;

/**
 * Onboarding documents are managed with "onboarding.manage".
 */
class OnboardingDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return modules()->onboarding() && $user->hasPermission('onboarding.manage');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, OnboardingDocument $document): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, OnboardingDocument $document): bool
    {
        return $this->viewAny($user);
    }
}
