<?php

namespace App\Policies;

use App\Models\DeliveryFollowUp;
use App\Models\User;

/**
 * Enersys deliveries follow the "enersys" permissions (605 module), electrical boards the
 * "board_deliveries" ones (their own module); always for projects the user can see.
 */
class DeliveryFollowUpPolicy
{
    /**
     * The permission area and whether its module is on, per kind.
     */
    public static function allows(User $user, string $kind, string $ability): bool
    {
        return match ($kind) {
            DeliveryFollowUp::ENERSYS => modules()->enersys() && $user->hasPermission('enersys.'.$ability),
            DeliveryFollowUp::BOARD => modules()->boardDeliveries() && $user->hasPermission('board_deliveries.'.$ability),
            default => false,
        };
    }

    public function view(User $user, DeliveryFollowUp $followUp): bool
    {
        return self::allows($user, $followUp->kind, 'view') && (bool) $followUp->project?->isVisibleTo($user);
    }

    public function update(User $user, DeliveryFollowUp $followUp): bool
    {
        return $this->view($user, $followUp) && self::allows($user, $followUp->kind, 'update');
    }

    public function delete(User $user, DeliveryFollowUp $followUp): bool
    {
        return $this->view($user, $followUp) && self::allows($user, $followUp->kind, 'delete');
    }
}
