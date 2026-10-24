<?php

namespace App\Filament\Resources\BatteryRooms\Pages;

use App\Actions\Batteries\BatteryRoomDocument;
use App\Filament\Resources\BatteryRooms\BatteryRoomResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\BatteryRoomDossier;
use App\Support\PdfAccess;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Fills in the battery room; the PDFs are made from the saved data, so the form is saved first.
 *
 * @property BatteryRoomDossier $record
 */
class EditBatteryRoom extends EditRecord
{
    protected static string $resource = BatteryRoomResource::class;

    public function getTitle(): string
    {
        return __('battery_rooms.button').' '.$this->record->project?->reference;
    }

    public function getSubheading(): ?string
    {
        return $this->record->cowAndRoom() ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make(array_map(fn (string $document): Action => Action::make('pdf_'.$document)
                ->label(__('battery_rooms.'.($document === 'checklist' ? 'pdf_checklist' : 'pdf_note')))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->action(function () use ($document): void {
                    if (Gate::allows('update', $this->record)) {
                        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    }

                    $this->js('window.open('.json_encode(route('battery-rooms.document', ['dossier' => $this->record, 'document' => $document])).', "_blank")');
                }), array_keys(BatteryRoomDocument::DOCUMENTS)))
                ->label('PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->button()
                ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'battery_rooms')),
            ActionGroup::make([
                Action::make('project')
                    ->label(ucfirst(__('erp.resources.project.singular')))
                    ->icon(Heroicon::OutlinedFolderOpen)
                    ->visible(fn (): bool => $this->record->project !== null && Gate::allows('view', $this->record->project))
                    ->url(fn (): string => ProjectResource::getUrl('view', ['record' => $this->record->project])),
                DeleteAction::make(),
            ])->color('gray'),
        ];
    }
}
