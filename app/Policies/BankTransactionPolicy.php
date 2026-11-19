<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Bank movements ("banking"): view lists them, match links them to invoices (or ignores them),
 * import reads CODA files or starts a Ponto read. Movements are never made or changed by hand.
 */
class BankTransactionPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'banking';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function match(User $user, Model $model): bool
    {
        return $this->allows($user, 'match');
    }

    public function import(User $user): bool
    {
        return $this->allows($user, 'import');
    }
}
