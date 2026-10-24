<?php

namespace App\Filament\Resources\BaeOrders\Pages;

use App\Actions\Batteries\BaeOrderDocument;
use App\Actions\Batteries\CreateBaeOrder;
use App\Filament\Resources\BaeOrders\BaeOrderResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\BaeOrder;
use App\Models\BatteryDelivery;
use App\Models\BatteryDossier;
use App\Support\PdfAccess;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Fills in the BAE order and exports it: the export saves the form first, then opens the PDF.
 *
 * @property BaeOrder $record
 */
class EditBaeOrder extends EditRecord
{
    protected static string $resource = BaeOrderResource::class;

    public function getTitle(): string
    {
        return __('erp.batteries.bae_order.singular').' '.$this->record->project?->reference;
    }

    public function getSubheading(): ?string
    {
        return $this->record->cowLine();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('erp.batteries.bae_order.export'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->tooltip(__('erp.batteries.bae_order.export_help'))
                ->visible(fn (): bool => PdfAccess::canGenerate(auth()->user(), 'batteries'))
                ->action(function (): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->js('window.open('.json_encode(route('batteries.bae-order-pdf', $this->record)).', "_blank")');
                }),
            ActionGroup::make([
                Action::make('fromDossier')
                    ->label(__('erp.batteries.bae_order.from_dossier'))
                    ->icon(Heroicon::OutlinedBattery100)
                    ->visible(fn (): bool => $this->dossier() !== null)
                    ->requiresConfirmation()
                    ->modalDescription(__('erp.batteries.bae_order.from_dossier_help'))
                    ->action(function (): void {
                        $this->fillWith(CreateBaeOrder::fromDossier($this->dossier()));
                        Notification::make()->title(__('erp.batteries.bae_order.from_dossier_done'))->success()->send();
                    }),
                Action::make('refillLines')
                    ->label(__('erp.batteries.bae_order.refill_lines'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalDescription(__('erp.batteries.bae_order.refill_lines_help'))
                    ->action(fn () => $this->fillWith(CreateBaeOrder::fromCatalogue(
                        filled($this->data['battery_model_id'] ?? null) ? (int) $this->data['battery_model_id'] : null,
                        is_string($this->data['mounting'] ?? null) ? $this->data['mounting'] : ($this->data['mounting'] ?? null)?->value,
                    ))),
                Action::make('toDelivery')
                    ->label(__('erp.batteries.bae_order.to_delivery'))
                    ->icon(Heroicon::OutlinedTruck)
                    ->visible(fn (): bool => ($delivery = $this->delivery()) !== null && Gate::allows('manageDocuments', $delivery) && PdfAccess::canGenerate(auth()->user(), 'batteries'))
                    ->requiresConfirmation()
                    ->modalDescription(__('erp.batteries.bae_order.to_delivery_help'))
                    ->action(function (BaeOrderDocument $document): void {
                        $delivery = $this->delivery();
                        abort_unless($delivery && Gate::allows('manageDocuments', $delivery), 403);

                        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                        $name = BaeOrderDocument::filename($this->record);
                        $path = 'battery-deliveries/'.$delivery->id.'/'.$name;
                        Storage::disk(BatteryDelivery::DISK)->put($path, $document->render($this->record));
                        // The same path again: the model must not delete the file it has just been given.
                        if ($delivery->order_document !== $path) {
                            $delivery->update(['order_document' => $path, 'order_document_name' => $name]);
                        }
                        $this->record->forceFill(['exported_at' => now()])->saveQuietly();

                        Notification::make()->title(__('erp.batteries.bae_order.to_delivery_done'))->success()->send();
                    }),
                Action::make('project')
                    ->label(ucfirst(__('erp.resources.project.singular')))
                    ->icon(Heroicon::OutlinedFolderOpen)
                    ->visible(fn (): bool => $this->record->project !== null && Gate::allows('view', $this->record->project))
                    ->url(fn (): string => ProjectResource::getUrl('view', ['record' => $this->record->project])),
                DeleteAction::make(),
            ])->color('gray'),
        ];
    }

    /**
     * Puts values in the form (not saved yet), e.g. from the dossier or the catalogue.
     *
     * @param  array<string, mixed>  $values
     */
    protected function fillWith(array $values): void
    {
        $this->form->fill([...$this->form->getRawState(), ...$values]);
    }

    protected function dossier(): ?BatteryDossier
    {
        return BatteryDossier::query()->where('project_id', $this->record->project_id)->latest('id')->first();
    }

    protected function delivery(): ?BatteryDelivery
    {
        return BatteryDelivery::query()->where('project_id', $this->record->project_id)->latest('id')->first();
    }
}
