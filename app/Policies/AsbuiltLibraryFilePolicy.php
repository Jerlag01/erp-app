<?php

namespace App\Policies;

use App\Models\AsbuiltLibraryFile;
use App\Models\User;

/**
 * The register of standard as-built files: kept with "asbuilt.library"; read (and downloaded) also
 * with "asbuilt.view" and "asbuilt.manage", to pick technical documents.
 */
class AsbuiltLibraryFilePolicy
{
    public function viewAny(User $user): bool
    {
        return modules()->asbuilt() && ($user->hasPermission('asbuilt.library') || $user->hasPermission('asbuilt.view') || $user->hasPermission('asbuilt.manage'));
    }

    public function view(User $user, AsbuiltLibraryFile $file): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return modules()->asbuilt() && $user->hasPermission('asbuilt.library');
    }

    public function update(User $user, AsbuiltLibraryFile $file): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, AsbuiltLibraryFile $file): bool
    {
        return $this->create($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }
}
