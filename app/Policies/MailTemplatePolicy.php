<?php

namespace App\Policies;

use App\Models\MailTemplate;
use App\Models\User;

/**
 * The texts of every system e-mail are managed by administrators.
 */
class MailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, MailTemplate $template): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MailTemplate $template): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, MailTemplate $template): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
