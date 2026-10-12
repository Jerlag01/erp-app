<?php

namespace App\Policies;

use App\Models\AssetCheck;
use App\Models\User;

/**
 * Administrators and "assets.control" publish, close and reopen the yearly control lists. Team
 * members only see (and fill in) the open list of their own team.
 */
class AssetCheckPolicy
{
    public static function enabled(): bool
    {
        return modules()->assets() && modules()->teams();
    }

    public static function manages(User $user): bool
    {
        return self::enabled() && $user->hasPermission('assets.control');
    }

    public function viewAny(User $user): bool
    {
        return self::manages($user)
            || (self::enabled() && AssetCheck::query()->open()->whereIn('team_id', $user->teamIds())->exists());
    }

    public function view(User $user, AssetCheck $check): bool
    {
        return self::manages($user) || $this->fill($user, $check);
    }

    /**
     * Mark equipment OK / not OK: only while the list is open.
     */
    public function fill(User $user, AssetCheck $check): bool
    {
        return self::enabled() && $check->isOpen()
            && (self::manages($user) || in_array($check->team_id, $user->teamIds(), true));
    }

    public function create(User $user): bool
    {
        return self::manages($user);
    }

    public function update(User $user, AssetCheck $check): bool
    {
        return self::manages($user);
    }

    public function delete(User $user, AssetCheck $check): bool
    {
        return self::manages($user);
    }

    public function deleteAny(User $user): bool
    {
        return self::manages($user);
    }
}
