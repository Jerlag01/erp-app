<?php

namespace App\Notifications\Client;

use App\Enums\MailTemplateKey;
use App\Mail\SystemMail;
use App\Models\ClientUser;
use App\Support\ClientPortal;
use Filament\Facades\Filament;
use Illuminate\Notifications\Notification;

/**
 * The "forgot password" mail of the client portal, built from the editable "client portal: new
 * password" template in the user's language. The link leads to the portal's own reset page.
 */
class ClientResetPassword extends Notification
{
    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(ClientUser $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(ClientUser $notifiable): SystemMail
    {
        $locale = $notifiable->preferredLocale() ?? config('app.locale');

        return (new SystemMail(MailTemplateKey::ClientPasswordReset, $locale, [
            'name' => $notifiable->name,
            'email' => $notifiable->email,
            'portal_name' => __('erp.client_portal.brand', locale: $locale),
            'portal_url' => ClientPortal::loginUrl(),
            'reset_link' => Filament::getPanel(ClientPortal::PANEL)->getResetPasswordUrl($this->token, $notifiable),
            'minutes' => (int) config('auth.passwords.client_users.expire', 60),
        ]))->to($notifiable->email, $notifiable->name);
    }
}
