<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Onboarding as OnboardingPage;
use App\Models\User;
use App\Support\Onboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A new user first reads and signs the onboarding documents: every other page of the platform leads to
 * the onboarding page until that is done. Livewire calls (the onboarding page itself) pass.
 */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->isMethod('GET') || $request->hasHeader('X-Livewire') || ! app(Onboarding::class)->needsOnboarding($user)) {
            return $next($request);
        }

        $page = OnboardingPage::getUrl(panel: 'app');

        if ($request->url() === $page || $request->routeIs('onboarding.document')) {
            return $next($request);
        }

        return redirect($page);
    }
}
