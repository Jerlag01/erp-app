<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;

class PlansRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'plans';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::Plans;
    }
}
