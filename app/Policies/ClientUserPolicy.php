<?php

namespace App\Policies;

use App\Models\ClientUser;
use App\Models\User;

/**
 * Only administrators of the ERP manage the users of the client portal.
 */
class ClientUserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ClientUser $client): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ClientUser $client): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ClientUser $client): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
