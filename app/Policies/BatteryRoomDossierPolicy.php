<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Battery rooms follow the "battery_rooms" permissions, for 609 projects the user can see.
 */
class BatteryRoomDossierPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'battery_rooms';
    }

    public function viewAny(User $user): bool
    {
        return modules()->batteryRooms() && parent::viewAny($user);
    }

    public function view(User $user, Model $dossier): bool
    {
        return modules()->batteryRooms() && $this->allows($user, 'view') && $dossier->project->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return modules()->batteryRooms() && parent::create($user);
    }

    public function update(User $user, Model $dossier): bool
    {
        return $this->view($user, $dossier) && $this->allows($user, 'update');
    }

    public function delete(User $user, Model $dossier): bool
    {
        return $this->view($user, $dossier) && $this->allows($user, 'delete');
    }
}
