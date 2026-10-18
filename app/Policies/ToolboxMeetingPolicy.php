<?php

namespace App\Policies;

use App\Models\ToolboxMeeting;
use App\Models\User;

/**
 * Every employee sees the published toolbox meetings and signs them. Making them needs
 * "toolbox.create" / "toolbox.update" / "toolbox.delete"; the list of signatures (and its overview)
 * "toolbox.view_signatures", the PDF also "toolbox.generate_pdf". Nothing while the module is off.
 */
class ToolboxMeetingPolicy
{
    public static function enabled(): bool
    {
        return modules()->toolbox();
    }

    /**
     * Who prepares meetings sees the drafts too.
     */
    public static function manages(User $user): bool
    {
        return self::enabled() && ($user->hasPermission('toolbox.create') || $user->hasPermission('toolbox.update'));
    }

    public static function seesSignatures(User $user): bool
    {
        return self::enabled() && $user->hasPermission('toolbox.view_signatures');
    }

    public function viewAny(User $user): bool
    {
        return self::enabled() && $user->is_active;
    }

    public function view(User $user, ToolboxMeeting $meeting): bool
    {
        return $this->viewAny($user) && ($meeting->isPublished() || self::manages($user) || self::seesSignatures($user));
    }

    public function create(User $user): bool
    {
        return self::enabled() && $user->hasPermission('toolbox.create');
    }

    public function update(User $user, ToolboxMeeting $meeting): bool
    {
        return self::enabled() && $user->hasPermission('toolbox.update');
    }

    public function delete(User $user, ToolboxMeeting $meeting): bool
    {
        return self::enabled() && $user->hasPermission('toolbox.delete');
    }

    public function deleteAny(User $user): bool
    {
        return self::enabled() && $user->hasPermission('toolbox.delete');
    }

    public function sign(User $user, ToolboxMeeting $meeting): bool
    {
        return $this->viewAny($user) && $meeting->isPublished();
    }
}
