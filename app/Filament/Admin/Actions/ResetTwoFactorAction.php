<?php

namespace App\Filament\Admin\Actions;

use App\Models\ClientUser;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;

/**
 * Switches two-step verification off for a user who lost their authenticator app or recovery codes:
 * administrators with the explicit permission "security.reset_two_factor" only. The user signs in
 * with their password again and can set it up anew in their profile.
 */
class ResetTwoFactorAction
{
    public const PERMISSION = 'security.reset_two_factor';

    public static function make(): Action
    {
        return Action::make('resetTwoFactor')
            ->label(__('erp.two_factor.reset'))
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('danger')
            ->visible(fn (User|ClientUser $record): bool => $record->hasTwoFactor() && self::allowed())
            ->authorize(fn (): bool => self::allowed())
            ->requiresConfirmation()
            ->modalHeading(fn (User|ClientUser $record): string => __('erp.two_factor.reset_heading', ['name' => $record->name]))
            ->modalDescription(__('erp.two_factor.reset_help'))
            ->action(function (User|ClientUser $record): void {
                $record->forceFill([
                    'app_authentication_secret' => null,
                    'app_authentication_recovery_codes' => null,
                    'has_email_authentication' => false,
                ])->save();

                Log::info('Two-step verification reset', ['user' => $record->getMorphClass().'#'.$record->getKey(), 'by' => auth()->id()]);
            })
            ->successNotificationTitle(__('erp.two_factor.reset_done'));
    }

    public static function allowed(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin() && $user->hasPermission(self::PERMISSION);
    }
}
