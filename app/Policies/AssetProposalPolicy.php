<?php

namespace App\Policies;

use App\Models\AssetProposal;
use App\Models\User;

/**
 * Proposed equipment is confirmed by administrators and "assets.control". Team members add it from
 * the open control list and may withdraw their own while it is still pending.
 */
class AssetProposalPolicy
{
    public function viewAny(User $user): bool
    {
        return AssetCheckPolicy::manages($user);
    }

    public function view(User $user, AssetProposal $proposal): bool
    {
        return AssetCheckPolicy::manages($user) || $proposal->proposed_by === $user->id;
    }

    public function update(User $user, AssetProposal $proposal): bool
    {
        return AssetCheckPolicy::manages($user) && $proposal->isPending();
    }

    public function delete(User $user, AssetProposal $proposal): bool
    {
        return $proposal->isPending() && (AssetCheckPolicy::manages($user) || $proposal->proposed_by === $user->id);
    }

    public function deleteAny(User $user): bool
    {
        return AssetCheckPolicy::manages($user);
    }
}
