<?php

namespace App\Filament\Resources\AssetChecks\Pages;

use App\Filament\Resources\AssetChecks\AssetCheckResource;
use App\Models\AssetCheck;
use App\Policies\AssetCheckPolicy;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ViewAssetCheck extends ViewRecord
{
    protected static string $resource = AssetCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('remarks')
                ->label(__('erp.asset_checks.remarks'))
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                ->color('gray')
                ->visible(fn (AssetCheck $record): bool => Gate::allows('fill', $record))
                ->fillForm(fn (AssetCheck $record): array => ['remarks' => $record->remarks])
                ->schema([
                    Textarea::make('remarks')
                        ->label(__('erp.asset_checks.remarks'))
                        ->helperText(__('erp.asset_checks.remarks_help'))
                        ->rows(8)
                        ->maxLength(10000),
                ])
                ->action(function (array $data, AssetCheck $record): void {
                    abort_unless(Gate::allows('fill', $record), 403);
                    $record->update(['remarks' => $data['remarks'] ?: null, 'remarks_by' => auth()->id(), 'remarks_at' => now()]);
                    Notification::make()->title(__('erp.asset_checks.remarks_saved'))->success()->send();
                }),
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
