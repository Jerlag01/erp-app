<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Welcome mail for a new employee: where to log in, with which e-mail address, and a link
 * (valid for WELCOME_LINK_DAYS days, usable until a password is set) to choose a password.
 * Sent in the language set on the account.
 */
class WelcomeEmployee extends Notification
{
    public const WELCOME_LINK_DAYS = 7;

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public static function linkFor(User $user): string
    {
        return URL::temporarySignedRoute('welcome', now()->addDays(self::WELCOME_LINK_DAYS), [
            'user' => $user->id,
            'h' => self::passwordFingerprint($user),
        ]);
    }

    /**
     * Part of a hash of the current password hash: once the password changes the link stops working.
     */
    public static function passwordFingerprint(User $user): string
    {
        return substr(hash('sha256', (string) $user->getAuthPassword()), 0, 16);
    }

    public function toMail(User $notifiable): MailMessage
    {
        $site = settings()->siteName();

        return (new MailMessage)
            ->subject(__('erp.employees.welcome.subject', ['site' => $site]))
            ->greeting(__('erp.employees.welcome.greeting', ['name' => $notifiable->name]))
            ->line(__('erp.employees.welcome.intro', ['site' => $site]))
            ->line(__('erp.employees.welcome.login_with', ['email' => $notifiable->email]))
            ->action(__('erp.employees.welcome.button'), self::linkFor($notifiable))
            ->line(__('erp.employees.welcome.steps', ['url' => url('/login')]))
            ->line(__('erp.employees.welcome.expiry', ['days' => self::WELCOME_LINK_DAYS]))
            ->salutation(__('erp.employees.welcome.salutation', ['site' => $site]));
    }
}
