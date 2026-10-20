<?php

namespace App\Notifications\Client;

use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\ClientUser;
use App\Support\ClientPortal;
use App\Support\MailTemplates;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

/**
 * Welcome mail for a new client portal user: where to log in, with which e-mail address, what they may
 * see, and a link (valid for WELCOME_LINK_DAYS days, usable until a password is set) to choose a
 * password. Built from the editable "client portal: welcome" template, in the user's language.
 */
class ClientWelcome extends Notification
{
    public const WELCOME_LINK_DAYS = 7;

    /**
     * @return list<string>
     */
    public function via(ClientUser $notifiable): array
    {
        return ['mail'];
    }

    public static function linkFor(ClientUser $user): string
    {
        return URL::temporarySignedRoute('client.welcome', now()->addDays(self::WELCOME_LINK_DAYS), [
            'clientUser' => $user->id,
            'h' => self::passwordFingerprint($user),
        ]);
    }

    /**
     * Part of a hash of the current password hash: once the password changes the link stops working.
     */
    public static function passwordFingerprint(ClientUser $user): string
    {
        return substr(hash('sha256', (string) $user->getAuthPassword()), 0, 16);
    }

    /**
     * The overviews the user may open, as a Markdown list.
     */
    public static function overviews(ClientUser $user): HtmlString
    {
        $lines = [];

        foreach (ClientPortal::PERMISSIONS as $permission) {
            if (! $user->may($permission)) {
                continue;
            }

            $line = __("erp.client_portal.permissions.{$permission}", locale: $user->preferredLocale());

            if ($permission === ClientPortal::PROJECTS && $user->projectCategories() !== []) {
                $line .= ' ('.implode(', ', $user->projectCategories()).')';
            }

            $lines[] = '- '.MailTemplates::escape($line);
        }

        return new HtmlString($lines === [] ? '- '.MailTemplates::escape(__('erp.client_portal.mail_nothing_yet', locale: $user->preferredLocale())) : implode("\n", $lines));
    }

    public function toMail(ClientUser $notifiable): SystemMail
    {
        $locale = $notifiable->preferredLocale() ?? config('app.locale');

        return (new SystemMail(MailTemplateKey::ClientWelcome, $locale, [
            'name' => $notifiable->name,
            'email' => $notifiable->email,
            'company' => $notifiable->company ?: 'Proximus',
            'portal_name' => __('erp.client_portal.brand', locale: $locale),
            'portal_url' => ClientPortal::loginUrl(),
            'password_link' => self::linkFor($notifiable),
            'link_days' => self::WELCOME_LINK_DAYS,
            'overviews' => self::overviews($notifiable),
        ]))->to($notifiable->email, $notifiable->name);
    }
}
