<?php

namespace App\Http\Controllers;

use App\Models\ClientUser;
use App\Models\Router;
use App\Support\ClientPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The VPN configuration of a 4G router for a client portal user with the "router secrets" permission, for a router at one of their own sites (or any, with "all projects").
 */
class ClientRouterVpnController
{
    public function __invoke(Router $router): StreamedResponse
    {
        $user = Auth::guard(ClientPortal::GUARD)->user();
        abort_unless($user instanceof ClientUser && $user->is_active && $user->may(ClientPortal::ROUTERS) && $user->may(ClientPortal::ROUTER_SECRETS) && modules()->charging(), 403);
        abort_unless($router->work_site_id !== null && $user->restrictToOwnSites(Router::query())->whereKey($router->getKey())->exists(), 403);
        abort_unless(filled($router->vpn_file) && Storage::disk(Router::DISK)->exists($router->vpn_file), 404);

        return Storage::disk(Router::DISK)->download($router->vpn_file, $router->vpn_file_name ?: basename($router->vpn_file), ['Cache-Control' => 'private, no-store']);
    }
}
