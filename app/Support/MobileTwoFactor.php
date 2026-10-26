<?php

namespace App\Support;

use App\Models\User;
use Filament\Auth\MultiFactor\Email\Notifications\VerifyEmailAuthentication;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * The second step when signing in on the mobile app, for users who switched on two-step verification
 * in their profile: a code of their authenticator app (or one of its recovery codes) or a code sent by
 * e-mail, valid for 10 minutes.
 */
class MobileTwoFactor
{
    public const EMAIL_MINUTES = 10;

    /**
     * Which methods the user has: "app", "email".
     *
     * @return list<string>
     */
    public function methods(User $user): array
    {
        return array_values(array_filter([
            filled($user->getAppAuthenticationSecret()) ? 'app' : null,
            $user->hasEmailAuthentication() ? 'email' : null,
        ]));
    }

    /**
     * Mails a fresh code (users with e-mail codes); a newer one replaces the older.
     */
    public function sendEmailCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->cacheKey($user), Hash::make($code), now()->addMinutes(self::EMAIL_MINUTES));

        $user->notify(new VerifyEmailAuthentication($code, self::EMAIL_MINUTES));
    }

    /**
     * Checks a code: of the authenticator app, a recovery code (used only once), or the e-mailed code.
     */
    public function verify(User $user, string $code): bool
    {
        $code = trim($code);

        if ($code === '') {
            return false;
        }

        if (filled($secret = $user->getAppAuthenticationSecret())) {
            if (preg_match('/^\d{6}$/', $code) && app(Google2FA::class)->verifyKey($secret, $code, 1)) {
                return true;
            }

            $codes = (array) $user->getAppAuthenticationRecoveryCodes();

            foreach ($codes as $index => $hashed) {
                if (Hash::check($code, (string) $hashed)) {
                    unset($codes[$index]);
                    $user->saveAppAuthenticationRecoveryCodes(array_values($codes));

                    return true;
                }
            }
        }

        if ($user->hasEmailAuthentication() && ($hashed = Cache::get($this->cacheKey($user))) && Hash::check($code, (string) $hashed)) {
            Cache::forget($this->cacheKey($user));

            return true;
        }

        return false;
    }

    protected function cacheKey(User $user): string
    {
        return 'mobile-two-factor:'.$user->id;
    }
}
