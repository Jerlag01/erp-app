<?php

namespace App\Policies;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Offers follow the "offers" permissions (module Offers, with or without finance). Once invoiced an offer can no longer be deleted and its
 * form is read-only; making the invoice also needs the finance
 * module and "invoices.create".
 */
class OfferPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'offers';
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Offer && $model->invoice_id === null && $this->allows($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function generatePdf(User $user, Offer $offer): bool
    {
        return $this->allows($user, 'generate_pdf');
    }

    /**
     * The cost prices and profit factors of the lines (the warehouse prices hold distributor agreements).
     */
    public function viewPrices(User $user): bool
    {
        return $this->allows($user, 'view_prices');
    }

    public function createInvoice(User $user, Offer $offer): bool
    {
        return modules()->finance() && $offer->invoice_id === null && $offer->client_id !== null && $offer->status !== OfferStatus::Rejected
            && $offer->lines()->whereNotNull('quantity')->exists()
            && $this->allows($user, 'update') && InvoicePolicy::allows($user, null, 'create');
    }
}
