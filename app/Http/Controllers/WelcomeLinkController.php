<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\WelcomeEmployee;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Opens the welcome link: a fresh password reset token is made at that moment and the employee
 * lands on the "choose a password" page. Once a password is set the link no longer works;
 * "forgot password" on the login page always remains available.
 */
class WelcomeLinkController
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        if (! $user->is_active || ! hash_equals(WelcomeEmployee::passwordFingerprint($user), (string) $request->query('h'))) {
            return redirect(Filament::getPanel('app')->getLoginUrl())
                ->with('status', __('erp.employees.welcome.link_used'));
        }

        $token = Password::broker(config('auth.defaults.passwords'))->createToken($user);

        return redirect(Filament::getPanel('app')->getResetPasswordUrl($token, $user));
    }
}
