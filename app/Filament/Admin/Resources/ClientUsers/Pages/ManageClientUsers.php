<?php

namespace App\Filament\Admin\Resources\ClientUsers\Pages;

use App\Actions\ClientPortal\SendClientWelcomeMail;
use App\Filament\Admin\Resources\ClientUsers\ClientUserResource;
use App\Models\ClientUser;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ManageClientUsers extends ManageRecords
{
    protected static string $resource = ClientUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('portal')
                ->label(__('erp.client_portal.open_portal'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(url('/client'), shouldOpenInNewTab: true),
            CreateAction::make()
                ->using(function (array $data): ClientUser {
                    $sendWelcome = (bool) ($data['send_welcome_mail'] ?? false);
                    unset($data['send_welcome_mail']);
                    // An unknown random password until the user chooses one through the welcome link.
                    $data['password'] ??= Str::password(40);

                    $user = ClientUser::query()->create($data);

                    if ($sendWelcome) {
                        ClientUserResource::notifyWelcome($user, app(SendClientWelcomeMail::class)->handle($user));
                    }

                    return $user;
                }),
        ];
    }
}
