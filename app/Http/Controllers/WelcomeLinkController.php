<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\WelcomeEmployee;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Password;

/**
 * Opens the welcome link: the employee lands on the "choose a password" page with a password reset
 * token. Once a password is set the link no longer works; "forgot password" on the login page always
 * remains available.
 *
 * Opening the link again (a second click or tab, or a mail scanner such as Outlook Safe Links that
 * opens it before the employee does) gives the same token as long as it is valid: a new token would
 * replace the previous one, and the page opened first would then say the token is invalid.
 */
class WelcomeLinkController
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        if (! $user->is_active || ! hash_equals(WelcomeEmployee::passwordFingerprint($user), (string) $request->query('h'))) {
            return redirect(Filament::getPanel('app')->getLoginUrl())
                ->with('status', __('erp.employees.welcome.link_used'));
        }

        return redirect(Filament::getPanel('app')->getResetPasswordUrl($this->token($user), $user));
    }

    protected function token(User $user): string
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker(config('auth.defaults.passwords'));
        $key = 'welcome-token:'.$user->id.':'.WelcomeEmployee::passwordFingerprint($user);
        $cached = rescue(fn (): ?string => ($value = Cache::get($key)) ? Crypt::decryptString($value) : null, null, false);

        if ($cached !== null && $broker->tokenExists($user, $cached)) {
            return $cached;
        }

        $token = $broker->createToken($user);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        Cache::put($key, Crypt::encryptString($token), now()->addMinutes(max(1, $minutes - 1)));

        return $token;
    }
}
