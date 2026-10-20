<?php

namespace App\Http\Controllers;

use App\Models\ClientUser;
use App\Notifications\Client\ClientWelcome;
use App\Support\ClientPortal;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Password;

/**
 * Opens the welcome link of a client portal user: they land on the portal's "choose a password" page
 * with a reset token. Works like WelcomeLinkController: once a password is set the link stops working,
 * and opening it again (or a mail scanner opening it first) gives the same token while it is valid.
 */
class ClientWelcomeLinkController
{
    public function __invoke(Request $request, ClientUser $clientUser): RedirectResponse
    {
        $panel = Filament::getPanel(ClientPortal::PANEL);

        if (! $clientUser->is_active || ! hash_equals(ClientWelcome::passwordFingerprint($clientUser), (string) $request->query('h'))) {
            return redirect($panel->getLoginUrl())->with('status', __('erp.client_portal.welcome_link_used'));
        }

        return redirect($panel->getResetPasswordUrl($this->token($clientUser), $clientUser));
    }

    protected function token(ClientUser $user): string
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker('client_users');
        $key = 'client-welcome-token:'.$user->id.':'.ClientWelcome::passwordFingerprint($user);
        $cached = rescue(fn (): ?string => ($value = Cache::get($key)) ? Crypt::decryptString($value) : null, null, false);

        if ($cached !== null && $broker->tokenExists($user, $cached)) {
            return $cached;
        }

        $token = $broker->createToken($user);
        $minutes = (int) config('auth.passwords.client_users.expire', 60);
        Cache::put($key, Crypt::encryptString($token), now()->addMinutes(max(1, $minutes - 1)));

        return $token;
    }
}
