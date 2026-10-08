<?php

namespace App\Filament\Resources\Invoices\Concerns;

use App\Enums\InvoiceType;
use App\Policies\InvoicePolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;

/**
 * Invoices and credit notes share a model but have separate resources and permissions.
 */
trait AuthorizesInvoiceType
{
    abstract public static function invoiceType(): InvoiceType;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', static::invoiceType()->value);
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        $user = auth()->user();

        return $user && InvoicePolicy::allows($user, static::invoiceType(), 'view') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        $user = auth()->user();

        // Credit notes are always made from an invoice (see CreateCreditNote).
        return $user && static::invoiceType() === InvoiceType::Invoice && InvoicePolicy::allows($user, InvoiceType::Invoice, 'create')
            ? Response::allow()
            : Response::deny();
    }
}
