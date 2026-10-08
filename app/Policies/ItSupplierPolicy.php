<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * IT master data: manufacturers and suppliers of the IT module. Records still in use cannot be deleted.
 */
class ItSupplierPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'it_master_data';
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! $model->isInUse();
    }
}
