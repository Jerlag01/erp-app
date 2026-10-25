<?php

namespace App\Policies;

use App\Models\OnboardingSignature;
use App\Models\User;

/**
 * Who signed the onboarding documents: administrators and "onboarding.view_signatures". Signatures are
 * never changed or removed by hand.
 */
class OnboardingSignaturePolicy
{
    public function viewAny(User $user): bool
    {
        return modules()->onboarding() && $user->hasPermission('onboarding.view_signatures');
    }

    public function view(User $user, OnboardingSignature $signature): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, OnboardingSignature $signature): bool
    {
        return false;
    }

    public function delete(User $user, OnboardingSignature $signature): bool
    {
        return false;
    }
}
