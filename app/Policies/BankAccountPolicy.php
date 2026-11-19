<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Bank accounts ("banking"): view shows the accounts and balances, manage adds or changes them and
 * connects Ponto. Only reading: nothing is ever paid from the ERP.
 */
class BankAccountPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'banking';
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, 'manage');
    }

    public function delete(User $user, Model $account): bool
    {
        return $this->allows($user, 'manage');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'manage');
    }

    public function import(User $user): bool
    {
        return $this->allows($user, 'import');
    }
}
