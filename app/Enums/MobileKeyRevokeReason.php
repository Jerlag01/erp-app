<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why a key of the mobile app no longer works.
 */
enum MobileKeyRevokeReason: string implements HasLabel
{
    /**
     * The user signed out in the app.
     */
    case Logout = 'logout';

    /**
     * The user signed in again on the same device, which got a new key.
     */
    case Replaced = 'replaced';

    /**
     * The login was switched off, e.g. because the employee left.
     */
    case UserInactive = 'user_inactive';

    /**
     * An administrator revoked the key.
     */
    case Admin = 'admin';

    public function getLabel(): string
    {
        return __('erp.enums.mobile_key_revoke_reason.'.$this->value);
    }
}
