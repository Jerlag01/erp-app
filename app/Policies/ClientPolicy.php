<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Clients follow the "clients" permissions. A client with invoices cannot be deleted.
 */
class ClientPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'clients';
    }

    public function delete(User $user, Model $client): bool
    {
        return parent::delete($user, $client) && ! $client->invoices()->exists();
    }
}
