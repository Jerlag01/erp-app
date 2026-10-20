<?php

namespace App\Actions\ClientPortal;

use App\Models\ClientUser;
use App\Notifications\Client\ClientWelcome;
use Throwable;

/**
 * Sends (or resends) the welcome mail to a client portal user. Returns false when the account is
 * inactive or the mail could not be sent (e.g. mail is not configured yet), so the administrator can
 * be told and resend it later.
 */
class SendClientWelcomeMail
{
    public function handle(ClientUser $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        try {
            $user->notify(new ClientWelcome);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        $user->forceFill(['welcome_sent_at' => now()])->save();

        return true;
    }
}
