<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectFileSection;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

/**
 * The reports of the BTV controls (uploaded in the BTV controls overview). Shown once the project has
 * a BTV control or such a report.
 */
class BtvControlFilesRelationManager extends ProjectFilesRelationManager
{
    protected static string $relationship = 'btvControlFiles';

    public static function section(): ProjectFileSection
    {
        return ProjectFileSection::BtvControl;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Project $ownerRecord */
        return ($ownerRecord->btvControls()->exists() || $ownerRecord->btvControlFiles()->exists()) && parent::canViewForRecord($ownerRecord, $pageClass);
    }
}
