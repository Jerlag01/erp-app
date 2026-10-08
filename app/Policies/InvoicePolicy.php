<?php

namespace App\Policies;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\User;

/**
 * Invoices follow the "invoices" permissions, credit notes the "credit_notes" ones. Only drafts
 * can be edited or deleted; a final document is corrected with a credit note. A credit note's
 * copied content can only be changed with "credit_notes.override" once switched on.
 */
class InvoicePolicy
{
    public static function area(?InvoiceType $type): string
    {
        return $type === InvoiceType::CreditNote ? 'credit_notes' : 'invoices';
    }

    public static function allows(User $user, ?InvoiceType $type, string $ability): bool
    {
        return modules()->finance() && $user->hasPermission(self::area($type).'.'.$ability);
    }

    public function viewAny(User $user): bool
    {
        return self::allows($user, InvoiceType::Invoice, 'view') || self::allows($user, InvoiceType::CreditNote, 'view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return self::allows($user, $invoice->type, 'view');
    }

    public function create(User $user): bool
    {
        return self::allows($user, InvoiceType::Invoice, 'create') || self::allows($user, InvoiceType::CreditNote, 'create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && self::allows($user, $invoice->type, 'update');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && self::allows($user, $invoice->type, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function finalize(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && $invoice->lines()->exists() && self::allows($user, $invoice->type, 'update');
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return ! $invoice->isDraft() && self::allows($user, $invoice->type, 'send');
    }

    public function markPaid(User $user, Invoice $invoice): bool
    {
        return ! $invoice->isDraft() && ! $invoice->isPaid() && ! $invoice->isCreditNote() && self::allows($user, $invoice->type, 'update');
    }

    public function credit(User $user, Invoice $invoice): bool
    {
        return ! $invoice->isCreditNote() && ! $invoice->isDraft() && self::allows($user, InvoiceType::CreditNote, 'create');
    }

    public function overrideCopiedContent(User $user, Invoice $invoice): bool
    {
        return $invoice->isCreditNote() && $invoice->isDraft() && self::allows($user, InvoiceType::CreditNote, 'override');
    }
}
