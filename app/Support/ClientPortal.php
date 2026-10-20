<?php

namespace App\Support;

use App\Models\ClientUser;
use Filament\Facades\Filament;

/**
 * The client portal ("Global overview for Client"): a separate panel with its own users, showing
 * projects, battery deliveries, charging stations and 4G routers read-only. Prices are never shown;
 * logins, recovery codes and VPN files only with their own permission.
 */
class ClientPortal
{
    public const PANEL = 'client';

    public const GUARD = 'client';

    /** The project categories the portal can show. */
    public const CATEGORIES = ['600', '604', '605', '608', '610', '620', '630', '640', '650'];

    public const PROJECTS = 'projects';

    public const BATTERY_DELIVERIES = 'battery_deliveries';

    public const CHARGING_STATIONS = 'charging_stations';

    public const CHARGING_SECRETS = 'charging_secrets';

    public const ROUTERS = 'routers';

    public const ROUTER_SECRETS = 'router_secrets';

    public const PERMISSIONS = [
        self::PROJECTS, self::BATTERY_DELIVERIES, self::CHARGING_STATIONS, self::CHARGING_SECRETS, self::ROUTERS, self::ROUTER_SECRETS,
    ];

    /**
     * The signed-in client user, on the portal.
     */
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
