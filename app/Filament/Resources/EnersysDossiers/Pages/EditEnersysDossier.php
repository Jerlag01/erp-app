<?php

namespace App\Filament\Resources\EnersysDossiers\Pages;

use App\Filament\Resources\BatteryDossiers\Pages\EditBatteryDossier;
use App\Filament\Resources\EnersysDossiers\EnersysDossierResource;
use App\Models\EnersysDossier;

/**
 * Works like the BAE dossier page: PDFs, signatures, finishing and locking follow the Enersys rights.
 *
 * @property EnersysDossier $record
 */
class EditEnersysDossier extends EditBatteryDossier
{
    protected static string $resource = EnersysDossierResource::class;

    public function getTitle(): string
    {
        return __('erp.enersys.dossier_title', ['reference' => $this->record->reference()]);
    }
}
