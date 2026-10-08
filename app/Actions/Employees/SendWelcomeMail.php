<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Notifications\WelcomeEmployee;
use Throwable;

/**
 * Sends (or resends) the welcome mail with login instructions and a link to choose a password.
 * Returns false when the mail could not be sent (e.g. mail is not configured yet), so the
 * administrator can be told and resend it later.
 */
class SendWelcomeMail
{
    public function handle(Employee $employee): bool
    {
        $user = $employee->user;

        if ($user === null || ! $user->is_active) {
            return false;
        }

        try {
            $user->notify(new WelcomeEmployee);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        $employee->forceFill(['welcome_sent_at' => now()])->save();

        return true;
    }
}
