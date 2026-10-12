<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Actions\Employees\SendWelcomeMail;
use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

/**
 * Creating an account here (outside the employee register) also sends the welcome mail, unless that
 * is switched off. Without a password the user chooses one through the link in the mail.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // An unknown random password until the user chooses one through the welcome link.
        $data['password'] ??= Str::password(40);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! ($this->data['send_welcome_mail'] ?? false)) {
            return;
        }

        $user = $this->getRecord();

        if (app(SendWelcomeMail::class)->toUser($user)) {
            Notification::make()->title(__('erp.users.welcome_sent', ['email' => $user->email]))->success()->send();
        } else {
            Notification::make()->title(__('erp.users.welcome_failed'))->body(__('erp.users.welcome_failed_body'))->warning()->persistent()->send();
        }
    }
}
