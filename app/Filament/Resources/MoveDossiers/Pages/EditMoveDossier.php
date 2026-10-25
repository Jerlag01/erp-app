<?php

namespace App\Filament\Resources\MoveDossiers\Pages;

use App\Filament\Resources\BatteryDossiers\Pages\EditBatteryDossier;
use App\Filament\Resources\MoveDossiers\MoveDossierResource;
use App\Models\MoveDossier;

/**
 * Works like the BAE dossier page: PDFs, signatures, floor plan, finishing and locking, with the 605
 * (Enersys) rights. Finishing decommissions the source site when that was chosen.
 *
 * @property MoveDossier $record
 */
class EditMoveDossier extends EditBatteryDossier
{
    protected static string $resource = MoveDossierResource::class;

    public function getTitle(): string
    {
        return __('erp.moves.dossier_title', ['reference' => $this->record->reference()]);
    }
}
