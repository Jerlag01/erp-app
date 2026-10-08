<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;

class ImagesRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'images';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::Images;
    }
}
