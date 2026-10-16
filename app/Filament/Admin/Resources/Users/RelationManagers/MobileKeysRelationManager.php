<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Enums\MobileKeyRevokeReason;
use App\Models\MobileKey;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The devices the user signed in on with the mobile app. Administrators revoke a lost or replaced
 * phone here; switching the login off revokes every key by itself.
 */
class MobileKeysRelationManager extends RelationManager
{
    protected static string $relationship = 'mobileKeys';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedDevicePhoneMobile;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('erp.mobile.devices');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var User $ownerRecord */
        $usable = $ownerRecord->mobileKeys()->usable()->count();

        return $usable > 0 ? (string) $usable : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('device_name')
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('erp.mobile.no_devices'))
            ->emptyStateDescription(__('erp.mobile.no_devices_help'))
            ->emptyStateIcon(Heroicon::OutlinedDevicePhoneMobile)
            ->columns([
                TextColumn::make('device_name')
                    ->label(__('erp.mobile.device'))
                    ->placeholder(__('erp.mobile.unknown_device'))
                    ->weight('medium'),
                TextColumn::make('created_at')
                    ->label(__('erp.mobile.signed_in_at'))
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('last_used_at')
                    ->label(__('erp.mobile.last_used_at'))
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (MobileKey $record): ?string => $record->last_used_ip)
                    ->placeholder('—'),
                TextColumn::make('expires_at')
                    ->label(__('erp.mobile.expires_at'))
                    ->date('d/m/Y'),
                TextColumn::make('status')
                    ->label(__('erp.fields.status'))
                    ->state(fn (MobileKey $record): string => match (true) {
                        $record->isRevoked() => __('erp.mobile.status_revoked', ['reason' => $record->revoked_reason?->getLabel() ?? '—']),
                        $record->isExpired() => __('erp.mobile.status_expired'),
                        default => __('erp.mobile.status_active'),
                    })
                    ->badge()
                    ->color(fn (MobileKey $record): string => $record->isUsable() ? 'success' : 'gray'),
            ])
            ->headerActions([
                Action::make('revokeAll')
                    ->label(__('erp.mobile.revoke_all'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('erp.mobile.revoke_all_help'))
                    ->visible(fn (): bool => $this->getOwnerRecord()->mobileKeys()->usable()->exists())
                    ->action(function (): void {
                        MobileKey::revokeAllFor($this->getOwnerRecord(), MobileKeyRevokeReason::Admin);
                        Notification::make()->title(__('erp.mobile.revoked'))->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('erp.mobile.revoke'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('erp.mobile.revoke_help'))
                    ->visible(fn (MobileKey $record): bool => $record->isUsable())
                    ->action(function (MobileKey $record): void {
                        $record->revoke(MobileKeyRevokeReason::Admin);
                        Notification::make()->title(__('erp.mobile.revoked'))->success()->send();
                    }),
            ]);
    }
}
