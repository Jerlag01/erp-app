<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;

class ExtraFilesRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'extraFiles';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::Extra;
    }
}
