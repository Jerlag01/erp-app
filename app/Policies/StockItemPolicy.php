<?php

namespace App\Policies;

class StockItemPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'stock_items';
    }
}
