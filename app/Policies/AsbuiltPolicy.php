<?php

namespace App\Policies;

use App\Models\Asbuilt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * As-builts: the overview with "asbuilt.view"; adding and removing files and planning the BTV
 * inspection with "asbuilt.manage"; reopening a closed one with "asbuilt.reopen"; the register of
 * standard files with "asbuilt.library". Removing an as-built needs "asbuilt.delete", an explicit
 * permission (administrators only have it when ticked), and is only offered on the admin side. The employees on the project (who see it) can always open
 * and download its as-built.
 */
class AsbuiltPolicy
{
    public function viewAny(User $user): bool
    {
        return modules()->asbuilt() && $user->hasPermission('asbuilt.view');
    }

    public function view(User $user, Asbuilt $asbuilt): bool
    {
        return modules()->asbuilt() && ($user->hasPermission('asbuilt.view') || ($asbuilt->project !== null && Gate::forUser($user)->allows('view', $asbuilt->project)));
    }

    public function update(User $user, Asbuilt $asbuilt): bool
    {
        return $this->view($user, $asbuilt) && $user->hasPermission('asbuilt.manage') && ! $asbuilt->isClosed();
    }

    public function reopen(User $user, Asbuilt $asbuilt): bool
    {
        return $this->view($user, $asbuilt) && $user->hasPermission('asbuilt.reopen') && $asbuilt->isClosed();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Asbuilt $asbuilt): bool
    {
        return modules()->asbuilt() && $user->hasPermission('asbuilt.delete');
    }

    public function library(User $user): bool
    {
        return modules()->asbuilt() && $user->hasPermission('asbuilt.library');
    }
}
