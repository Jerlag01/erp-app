<?php

namespace App\Filament\Client;

use App\Support\ClientPortal;
use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * A read-only overview of the client portal. Access comes only from the client user's own permissions
 * (never from the ERP's policies, which are for ERP users): viewing is allowed with the overview's
 * permission while its module is on; creating, editing and deleting never.
 */
abstract class ClientResource extends Resource
{
    protected static bool $isGloballySearchable = false;

    /**
     * The permission of ClientPortal::PERMISSIONS that opens this overview.
     */
    abstract public static function permission(): string;

    public static function moduleEnabled(): bool
    {
        return true;
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;

        return in_array($action, ['viewAny', 'view'], true) && static::moduleEnabled() && ClientPortal::may(static::permission())
            ? Response::allow()
            : Response::deny();
    }
}
