<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Exports for the accountant ("accounting_export"): view downloads earlier ones, create makes a new
 * one, send mails it to the accountant. An export is removed by whoever may make them.
 */
class AccountingExportPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'accounting_export';
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, 'create');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function send(User $user, Model $model): bool
    {
        return $this->allows($user, 'send');
    }
}
