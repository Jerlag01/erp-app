<?php

namespace App\Models\Concerns;

use SensitiveParameter;

/**
 * Two-step verification for a user of a panel (Filament): an authenticator app with recovery codes and
 * a code by e-mail. The secret and the recovery codes are stored encrypted.
 */
trait HasTwoFactorAuthentication
{
    public function initializeHasTwoFactorAuthentication(): void
    {
        $this->mergeCasts([
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'has_email_authentication' => 'boolean',
        ]);
        $this->makeHidden(['app_authentication_secret', 'app_authentication_recovery_codes']);
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->forceFill(['app_authentication_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return (string) $this->email;
    }

    /**
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  ?array<string>  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->forceFill(['app_authentication_recovery_codes' => $codes])->save();
    }

    public function hasEmailAuthentication(): bool
    {
        return (bool) $this->has_email_authentication;
    }

    public function toggleEmailAuthentication(bool $condition): void
    {
        $this->forceFill(['has_email_authentication' => $condition])->save();
    }

    /**
     * Whether a second step is needed to sign in (an authenticator app or e-mail codes are on).
     */
    public function hasTwoFactor(): bool
    {
        return filled($this->app_authentication_secret) || $this->hasEmailAuthentication();
    }
}
