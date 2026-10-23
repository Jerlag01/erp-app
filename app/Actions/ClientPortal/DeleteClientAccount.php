<?php

namespace App\Actions\ClientPortal;

use App\Filament\Admin\Resources\ClientUsers\ClientUserResource;
use App\Models\ClientUser;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * A client portal user removes their own account. What they handed in (as-built files, GPW
 * decisions) stays with the projects, without their name; their access to projects is removed with
 * the account. The administrators are told in the platform.
 */
class DeleteClientAccount
{
    public function handle(ClientUser $user): void
    {
        $name = $user->name;
        $email = $user->email;
        $company = $user->company;

        DB::transaction(function () use ($user): void {
            DB::table('client_password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        });

        foreach (User::query()->active()->whereHas('role', fn ($query) => $query->where('is_admin', true))->get() as $admin) {
            $locale = $admin->preferredLocale();

            Notification::make()
                ->title(__('erp.client_portal.account_deleted.notification', ['name' => $name], $locale))
                ->body(trim($email.($company ? ' · '.$company : '')))
                ->icon('heroicon-o-user-minus')
                ->warning()
                ->actions([Action::make('open')->label(__('erp.client_portal.users', [], $locale))->url(ClientUserResource::getUrl(panel: 'admin'))])
                ->sendToDatabase($admin);
        }
    }
}
