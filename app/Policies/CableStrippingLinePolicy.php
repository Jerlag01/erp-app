<?php

namespace App\Policies;

/**
 * Cable stripping follows the "cable_stripping" permissions: everyone with access adds lines; removing
 * lines ("delete") and the PDF ("generate_pdf") need their own right.
 */
class CableStrippingLinePolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'cable_stripping';
    }
}
