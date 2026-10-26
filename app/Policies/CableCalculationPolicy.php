<?php

namespace App\Policies;

/**
 * The cable calculator follows the "cable_calculations" permissions; the PDF needs "generate_pdf".
 */
class CableCalculationPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'cable_calculations';
    }
}
