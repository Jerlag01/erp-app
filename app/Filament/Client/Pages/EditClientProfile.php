<?php

namespace App\Filament\Client\Pages;

use App\Actions\ClientPortal\DeleteClientAccount;
use App\Support\ClientPortal;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * The profile of a client portal user, with the option to remove their own account after a
 * warning, a tick and their password.
 */
class EditClientProfile extends EditProfile
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('deleteAccount')
                ->label(__('erp.client_portal.account_deleted.button'))
                ->icon(Heroicon::OutlinedUserMinus)
                ->color('danger')
                ->modalIcon(Heroicon::OutlinedExclamationTriangle)
                ->modalIconColor('danger')
                ->modalHeading(__('erp.client_portal.account_deleted.heading'))
                ->modalDescription(__('erp.client_portal.account_deleted.warning'))
                ->modalSubmitActionLabel(__('erp.client_portal.account_deleted.confirm'))
                ->schema([
                    Checkbox::make('understood')
                        ->label(__('erp.client_portal.account_deleted.understood'))
                        ->accepted(),
                    TextInput::make('password')
                        ->label(__('erp.client_portal.account_deleted.password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->currentPassword(guard: ClientPortal::GUARD),
                ])
                ->action(function (DeleteClientAccount $delete): void {
                    $user = ClientPortal::user();
                    abort_if($user === null, 403);

                    $delete->handle($user);

                    Auth::guard(ClientPortal::GUARD)->logout();
                    session()->invalidate();
                    session()->regenerateToken();
                    session()->flash('status', __('erp.client_portal.account_deleted.done'));

                    $this->redirect(filament()->getPanel(ClientPortal::PANEL)->getLoginUrl());
                }),
        ];
    }
}
