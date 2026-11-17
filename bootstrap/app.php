<?php

use App\Http\Middleware\AuthenticateMobileKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Plain routes (QR labels) send guests to the employee panel's login page.
        $middleware->redirectGuestsTo(fn () => route('filament.app.auth.login'));

        // The mobile app's key is checked BEFORE the rate limiter (and the route bindings): Laravel's priority
        // list otherwise runs the limiter first, without a user, so it counted per IP address and colleagues on
        // the same Wi-Fi or mobile network (shared IP) used up one limit together ("Too Many Attempts").
        $middleware->prependToPriorityList(ThrottleRequests::class, AuthenticateMobileKey::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
