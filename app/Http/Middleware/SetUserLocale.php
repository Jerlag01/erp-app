<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    /**
     * Show the panels in the signed-in user's preferred language.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($locale = $request->user()?->preferredLocale()) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
