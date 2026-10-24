<?php

namespace App\Filament\Resources\BaeOrders\Pages;

use App\Actions\Batteries\BaeOrderDocument;
use App\Actions\Batteries\CreateBaeOrder;
use App\Actions\Batteries\SendBaeOrder;
use App\Enums\MailTemplateKey;
use App\Filament\Admin\Resources\MailTemplates\MailTemplateResource;
use App\Filament\Resources\BaeOrders\BaeOrderResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\BaeOrder;
use App\Models\BatteryDelivery;
use App\Models\BatteryDossier;
use App\Models\BatterySupplier;
use App\Models\MailTemplate;
use App\Support\PdfAccess;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Fills in the BAE order, exports it and mails it to the supplier: both save the form first.
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
            // Mailed to the supplier only with this button, never automatically.
            Action::make('send')
                ->label(__('erp.batteries.bae_order.send'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('success')
                ->visible(fn (): bool => Gate::allows('update', $this->record))
                ->modalIcon(Heroicon::OutlinedPaperAirplane)
                ->modalHeading(fn (): string => __('erp.batteries.bae_order.send_heading', ['number' => $this->data['order_number'] ?? $this->record->order_number]))
                ->modalDescription(fn (): string => $this->sendDescription())
                ->modalSubmitActionLabel(__('erp.batteries.bae_order.send_submit'))
                ->schema([
                    Textarea::make('message')
                        ->label(__('erp.batteries.bae_order.send_message'))
                        ->helperText(__('erp.batteries.bae_order.send_message_help'))
                        ->rows(4)
                        ->maxLength(2000),
                ])
                ->action(function (array $data, Action $action, SendBaeOrder $sender): void {
                    abort_unless(Gate::allows('update', $this->record), 403);

                    try {
                        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    } catch (ValidationException $exception) {
                        $this->setErrorBag($exception->errors());
                        $this->sendRefused(__('erp.batteries.bae_order.send_incomplete'), $action);
                    }

                    $order = $this->record->refresh();

                    if (! $order->isComplete()) {
                        $this->sendRefused(__('erp.batteries.bae_order.send_incomplete'), $action);
                    }

                    if (! $order->supplier || blank($order->supplier->email)) {
                        $this->sendRefused(__('erp.batteries.bae_order.send_no_supplier'), $action);
                    }

                    try {
                        $sender->handle($order, auth()->user(), $data['message'] ?? null);
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->sendRefused(__('erp.batteries.bae_order.send_failed'), $action);
                    }

                    Notification::make()->title(__('erp.batteries.bae_order.sent_done', ['recipients' => $order->sent_to]))->success()->send();
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
                // The order mail is an editable template (per language) on the admin side.
                Action::make('mailTemplate')
                    ->label(__('erp.batteries.bae_order.mail_template'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->visible(fn (): bool => Gate::allows('viewAny', MailTemplate::class))
                    ->url(fn (): string => MailTemplateResource::getUrl('index', ['filters' => ['key' => ['value' => MailTemplateKey::BaeOrder->value]]], panel: 'admin'), shouldOpenInNewTab: true),
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
     * To whom the order goes (the supplier chosen in the form), and whether it went out before.
     */
    protected function sendDescription(): string
    {
        $supplier = BatterySupplier::query()->find($this->data['battery_supplier_id'] ?? null);

        if (! $supplier) {
            return __('erp.batteries.bae_order.send_no_supplier');
        }

        $text = __('erp.batteries.bae_order.send_help', ['recipients' => SendBaeOrder::recipientsText($supplier)]);

        if ($this->record->isSent()) {
            $text .= ' '.__('erp.batteries.bae_order.send_again_help', ['date' => $this->record->sent_at->format('d/m/Y H:i'), 'name' => $this->record->sender?->name ?? '—']);
        }

        return $text;
    }

    /**
     * Stops the sending with a message; the modal closes.
     */
    protected function sendRefused(string $message, Action $action): never
    {
        Notification::make()->title($message)->danger()->send();
        $action->cancel();
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
