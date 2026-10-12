<?php

namespace App\Filament\Resources\AssetChecks\Pages;

use App\Filament\Resources\AssetChecks\AssetCheckResource;
use App\Models\AssetCheck;
use App\Policies\AssetCheckPolicy;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewAssetCheck extends ViewRecord
{
    protected static string $resource = AssetCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label(__('erp.asset_checks.sync'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (AssetCheck $record): bool => $record->isOpen() && AssetCheckPolicy::manages(auth()->user()))
                ->action(function (AssetCheck $record): void {
                    Notification::make()->title(trans_choice('erp.asset_checks.synced', $record->syncItems()))->success()->send();
                }),
            AssetCheckResource::closeAction(),
            AssetCheckResource::reopenAction(),
        ];
    }
}
