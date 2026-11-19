<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The VAT codes are finance master data ("finance_master_data"). A code in use cannot be removed (switch it off instead).
 */
class VatCodePolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'finance_master_data';
    }

    public function delete(User $user, Model $code): bool
    {
        return parent::delete($user, $code) && ! $code->isUsed();
    }
}
