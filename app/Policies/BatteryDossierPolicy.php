<?php

namespace App\Policies;

use App\Models\BatteryDossier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Battery dossiers follow the "batteries" permissions, for dossiers on projects the user can see.
 * Prices and the invoice request need "batteries.prices".
 */
class BatteryDossierPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'batteries';
    }

    public function viewAny(User $user): bool
    {
        return modules()->batteries() && parent::viewAny($user);
    }

    public function view(User $user, Model $dossier): bool
    {
        return modules()->batteries() && $this->allows($user, 'view') && $dossier->project->isVisibleTo($user);
    }

    /**
     * A new dossier starts on the Input tab, so creating also needs "batteries.input".
     */
    public function create(User $user): bool
    {
        return modules()->batteries() && parent::create($user) && $this->allows($user, 'input');
    }

    /**
     * The Input tab (project data, batteries, prices); without it an overview of the project is shown.
     */
    public function viewInput(User $user, ?BatteryDossier $dossier = null): bool
    {
        return modules()->batteries() && $this->allows($user, 'input');
    }

    public function update(User $user, Model $dossier): bool
    {
        return $this->view($user, $dossier) && $this->allows($user, 'update');
    }

    public function delete(User $user, Model $dossier): bool
    {
        return $this->view($user, $dossier) && $this->allows($user, 'delete');
    }

    public function viewPrices(User $user, ?BatteryDossier $dossier = null): bool
    {
        return modules()->batteries() && $this->allows($user, 'prices');
    }
}
