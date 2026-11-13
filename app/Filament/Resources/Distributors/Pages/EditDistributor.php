<?php

namespace App\Filament\Resources\Distributors\Pages;

use App\Filament\Resources\Distributors\DistributorResource;
use App\Models\Distributor;
use App\Services\Distributors\DigiKeyClient;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDistributor extends EditRecord
{
    protected static string $resource = DistributorResource::class;

    protected function getHeaderActions(): array
    {
        $digiKey = fn (Distributor $record): ?DigiKeyClient => (bool) auth()->user()?->isAdmin() && ($client = $record->client()) instanceof DigiKeyClient && $client->isConfigured() ? $client : null;

        return [
            // The DigiKey user login (barcodes, accounts): DigiKey asks to log in and sends the user back.
            Action::make('connectDigiKey')
                ->label(__('erp.distributors.digikey.connect'))
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (Distributor $record): bool => $digiKey($record) !== null)
                ->url(fn (Distributor $record): string => route('distributors.digikey.connect', $record)),
            Action::make('disconnectDigiKey')
                ->label(__('erp.distributors.digikey.disconnect'))
                ->icon(Heroicon::OutlinedLinkSlash)
                ->color('gray')
                ->visible(fn (Distributor $record): bool => (bool) $digiKey($record)?->isConnected())
                ->requiresConfirmation()
                ->action(function (Distributor $record) use ($digiKey): void {
                    $digiKey($record)?->disconnect();
                    Notification::make()->title(__('erp.distributors.digikey.disconnected'))->success()->send();
                }),
            DeleteAction::make(),
        ];
    }
}
