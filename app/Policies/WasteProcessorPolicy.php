<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Waste processors follow the "waste_master_data" permissions. A processor that appears in the
 * registry cannot be deleted (switch it off instead).
 */
class WasteProcessorPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'waste_master_data';
    }

    public function delete(User $user, Model $processor): bool
    {
        return parent::delete($user, $processor) && ! $processor->entries()->exists();
    }
}
