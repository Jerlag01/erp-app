<?php

namespace App\Policies;

/**
 * Enersys dossiers (605) follow the "enersys" permissions, with the same rights as the BAE dossiers.
 */
class EnersysDossierPolicy extends BatteryDossierPolicy
{
    protected function area(): string
    {
        return 'enersys';
    }

    protected function moduleOn(): bool
    {
        return modules()->enersys();
    }
}
