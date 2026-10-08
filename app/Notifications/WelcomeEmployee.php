<?php

namespace App\Notifications;

use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\User;
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

    /**
     * Built from the editable "welcome employee" template, in the language of the account.
     */
    public function toMail(User $notifiable): SystemMail
    {
        return (new SystemMail(MailTemplateKey::WelcomeEmployee, $notifiable->preferredLocale() ?? config('app.locale'), [
            'name' => $notifiable->name,
            'email' => $notifiable->email,
            'login_url' => url('/login'),
            'password_link' => self::linkFor($notifiable),
            'link_days' => self::WELCOME_LINK_DAYS,
        ]))->to($notifiable->email);
    }
}
