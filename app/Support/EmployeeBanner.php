<?php

namespace App\Support;

/**
 * The message administrators can show to employees in the top bar of the employee side of the
 * platform (Settings > General), in the reader's language with Dutch as fallback.
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
        if (! settings()->get('banner.enabled')) {
            return null;
        }

        $locale ??= app()->getLocale();
        $message = settings()->get("banner.message_{$locale}") ?: settings()->get('banner.message_nl');

        return filled($message) ? trim((string) $message) : null;
    }

    public function rgb(): string
    {
        return self::COLORS[settings()->get('banner.color')] ?? self::COLORS['info'];
    }
}
