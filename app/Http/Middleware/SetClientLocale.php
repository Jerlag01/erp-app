<?php

namespace App\Http\Middleware;

use App\Support\ClientPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The client portal speaks the language set on the client user.
 */
class SetClientLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($locale = ClientPortal::user()?->preferredLocale()) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
