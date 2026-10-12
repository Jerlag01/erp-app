<?php

namespace App\Http\Controllers;

use App\Models\Router;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads the VPN file of a 4G router; only for users with "routers.secrets".
 */
class RouterVpnController extends Controller
{
    public function __invoke(Request $request, Router $router): Response
    {
        abort_unless(Gate::allows('viewSecrets', Router::class), 403);
        abort_unless(filled($router->vpn_file) && Storage::disk(Router::DISK)->exists($router->vpn_file), 404);

        return Storage::disk(Router::DISK)->download($router->vpn_file, $router->vpn_file_name ?: basename($router->vpn_file), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
