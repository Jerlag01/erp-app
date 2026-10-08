<?php

namespace App\Policies;

/**
 * The standard invoice lines (file costs, BTV inspection, …) follow "finance_master_data".
 */
class StandardInvoiceLinePolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'finance_master_data';
    }
}
