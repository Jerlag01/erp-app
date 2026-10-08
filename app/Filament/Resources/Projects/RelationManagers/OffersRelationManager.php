<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;

class OffersRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'offers';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::Offers;
    }
}
