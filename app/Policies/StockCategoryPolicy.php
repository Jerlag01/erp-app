<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Stock master data: manufacturers, distributors, contacts, categories and units.
 */
class StockCategoryPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'stock_master_data';
    }

    /**
     * A category that still has subcategories or items cannot be deleted.
     */
    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model)
            && ! $model->children()->exists()
            && ! $model->stockItems()->exists();
    }
}
