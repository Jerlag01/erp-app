<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;

/**
 * The extra files of a project. The PDFs of its cable calculations have their own tab
 * (CableCalculationFilesRelationManager).
 */
class ExtraFilesRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'extraFiles';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::Extra;
    }
}
