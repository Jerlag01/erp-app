<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InvoiceType: string implements HasLabel
{
    case Invoice = 'invoice';

    case CreditNote = 'credit_note';

    public function getLabel(): string
    {
        return __('erp.enums.invoice_type.'.$this->value);
    }

    /**
     * +1 for invoices, -1 for credit notes, for totals.
     */
    public function sign(): int
    {
        return $this === self::Invoice ? 1 : -1;
    }
}
