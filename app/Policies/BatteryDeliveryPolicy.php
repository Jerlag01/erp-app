<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The delivery overview follows the "batteries" permissions, for 604 projects the user can see.
 * The total cost needs "batteries.prices"; the documents "batteries.documents" (and
 * "batteries.documents_upload").
 */
class BatteryDeliveryPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'batteries';
    }

    public function viewAny(User $user): bool
    {
        return modules()->batteries() && parent::viewAny($user);
    }

    public function view(User $user, Model $delivery): bool
    {
        return modules()->batteries() && $this->allows($user, 'view') && $delivery->project->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return modules()->batteries() && parent::create($user);
    }

    public function update(User $user, Model $delivery): bool
    {
        return $this->view($user, $delivery) && $this->allows($user, 'update');
    }

    public function delete(User $user, Model $delivery): bool
    {
        return $this->view($user, $delivery) && $this->allows($user, 'delete');
    }

    /**
     * The order, confirmation and delivery note of a delivery: "batteries.documents" to see and
     * download them, "batteries.documents_upload" to add, replace or remove them.
     */
    public function viewDocuments(User $user, Model $delivery): bool
    {
        return $this->view($user, $delivery) && $this->allows($user, 'documents');
    }

    public function manageDocuments(User $user, Model $delivery): bool
    {
        return $this->viewDocuments($user, $delivery) && $this->allows($user, 'documents_upload');
    }

    public function viewPrices(User $user): bool
    {
        return modules()->batteries() && $this->allows($user, 'prices');
    }
}
