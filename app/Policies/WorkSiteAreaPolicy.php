<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Work site master data: areas and contact persons. Ones still in use cannot be deleted.
 */
class WorkSiteAreaPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'work_site_master_data';
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! $model->workSites()->exists();
    }
}
