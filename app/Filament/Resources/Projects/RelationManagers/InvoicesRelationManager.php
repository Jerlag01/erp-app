<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;

class InvoicesRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'invoices';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::Invoices;
    }
}
