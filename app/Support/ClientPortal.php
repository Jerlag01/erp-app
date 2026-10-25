<?php

namespace App\Support;

use App\Models\ClientUser;
use App\Models\ProjectCategory;
use Filament\Facades\Filament;

/**
 * The client portal ("Global overview for Client"): a separate panel with its own users, showing
 * projects, battery deliveries, charging stations and 4G routers read-only. A user sees only their own
 * projects (assigned to them, or at the COW sites of their contact person) unless they may see all.
 * Prices are never shown; logins, recovery codes and VPN files only with their own permission.
 */
class ClientPortal
{
    public const PANEL = 'client';

    public const GUARD = 'client';

    /** Project categories the portal never shows: private clients (606) and 607. */
    public const EXCLUDED_CATEGORIES = ['606', '607'];

    public const PROJECTS = 'projects';

    /** Projects of other clients too: without it a user sees only their own projects and COW sites. */
    public const ALL_PROJECTS = 'all_projects';

    /** See and approve (or refuse) the GPW requests of the projects the user sees. */
    public const GPW = 'gpw';

    /** Follow the as-builts of the projects the user sees, download them when finished and hand in files. */
    public const ASBUILT = 'asbuilt';

    public const BATTERY_DELIVERIES = 'battery_deliveries';

    public const CHARGING_STATIONS = 'charging_stations';

    public const CHARGING_SECRETS = 'charging_secrets';

    public const ROUTERS = 'routers';

    public const ROUTER_SECRETS = 'router_secrets';

    public const PERMISSIONS = [
        self::PROJECTS, self::ALL_PROJECTS, self::GPW, self::ASBUILT, self::BATTERY_DELIVERIES, self::CHARGING_STATIONS, self::CHARGING_SECRETS, self::ROUTERS, self::ROUTER_SECRETS,
    ];

    /**
     * The signed-in client user, on the portal.
     */
    /**
     * The project categories the portal can show: every category except the excluded ones, so new
     * categories (608.1, 609, …) can be given to client users too.
     *
     * @return list<string>
     */
    public static function categories(): array
    {
        return ProjectCategory::query()
            ->whereNotIn('code', self::EXCLUDED_CATEGORIES)
            ->orderBy('code')
            ->pluck('code')
            ->map(fn (mixed $code): string => (string) $code)
            ->values()
            ->all();
    }

    /**
     * "609 — Batterijlokalen" per category, for the client user form.
     *
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return ProjectCategory::query()
            ->whereIn('code', self::categories())
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (ProjectCategory $category): array => [(string) $category->code => $category->name && $category->name !== $category->code ? "{$category->code} — {$category->name}" : (string) $category->code])
            ->all();
    }

    public static function user(): ?ClientUser
    {
        $user = Filament::auth()->user();

        return $user instanceof ClientUser ? $user : null;
    }

    /**
     * Where client users sign in.
     */
    public static function loginUrl(): string
    {
        return Filament::getPanel(self::PANEL)->getLoginUrl();
    }

    public static function may(string $permission): bool
    {
        return (bool) self::user()?->may($permission);
    }
}
