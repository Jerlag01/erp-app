<?php

namespace App\Policies;

/**
 * Follows the "waste" permissions; nothing is allowed while the waste module is off.
 */
class WasteEntryPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'waste';
    }
}
