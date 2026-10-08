<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Waste categories follow the "waste_master_data" permissions. A category that is used in the
 * registry or still has subcategories cannot be deleted (switch it off instead).
 */
class WasteCategoryPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'waste_master_data';
    }

    public function delete(User $user, Model $category): bool
    {
        return parent::delete($user, $category) && ! $category->isInUse();
    }
}
