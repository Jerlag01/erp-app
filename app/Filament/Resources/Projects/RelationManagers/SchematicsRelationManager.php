<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;

class SchematicsRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'schematics';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::Schematics;
    }
}
