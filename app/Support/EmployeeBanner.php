<?php

namespace App\Support;

use App\Models\User;

/**
 * The announcement shown to employees in the top bar of the employee side of the platform (module
 * "announcements", edited by administrators and "announcements.update" on the Announcement page), in
 * the reader's language with Dutch as fallback.
 */
class EmployeeBanner
{
    /** The colours to choose from, as RGB for the inline styles. */
    public const COLORS = [
        'info' => '14 116 194',
        'success' => '22 163 74',
        'warning' => '217 119 6',
        'danger' => '220 38 38',
    ];

    public function message(?string $locale = null): ?string
    {
        if (! modules()->announcements() || ! settings()->get('banner.enabled')) {
            return null;
        }

        $locale ??= app()->getLocale();
        $message = settings()->get("banner.message_{$locale}") ?: settings()->get('banner.message_nl');

        return filled($message) ? trim((string) $message) : null;
    }

    /**
     * Who may edit it: administrators and "announcements.update", while the module is on.
     */
    public function canEdit(?User $user): bool
    {
        return $user !== null && modules()->announcements() && ($user->isAdmin() || $user->hasPermission('announcements.update'));
    }

    public function rgb(): string
    {
        return self::COLORS[settings()->get('banner.color')] ?? self::COLORS['info'];
    }
}
