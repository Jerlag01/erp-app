<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;

class DocumentsRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'documents';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::Documents;
    }
}
