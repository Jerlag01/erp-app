<?php

namespace App\Filament\Resources\BatteryDeliveries;

use App\Models\BatteryDelivery;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ViewColumn;
use Illuminate\Support\Facades\Gate;

/**
 * The three internal documents of a battery delivery (order to the supplier, the supplier's
 * confirmation, delivery note): a column with their links and an action with an upload zone per
 * document. Used in the delivery overview and on the 604 project. Never part of the client portal.
 */
class DeliveryDocuments
{
    /** PDF, pictures and the usual office files, up to 50 MB. */
    public const TYPES = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'message/rfc822', 'application/vnd.ms-outlook',
    ];

    public static function column(): ViewColumn
    {
        return ViewColumn::make('documents')
            ->label(__('erp.batteries.delivery_documents.title'))
            ->view('filament.batteries.delivery-documents')
            ->visible(fn (): bool => (bool) auth()->user()?->hasPermission('batteries.documents'));
    }

    public static function action(): Action
    {
        return Action::make('documents')
            ->label(__('erp.batteries.delivery_documents.title'))
            ->icon(Heroicon::OutlinedPaperClip)
            ->color('gray')
            ->visible(fn (BatteryDelivery $record): bool => Gate::allows('manageDocuments', $record))
            ->modalHeading(fn (BatteryDelivery $record): string => __('erp.batteries.delivery_documents.heading', ['order' => $record->orderNumber()]))
            ->modalDescription(__('erp.batteries.delivery_documents.help'))
            ->schema(fn (BatteryDelivery $record): array => array_map(fn (string $kind): Section => Section::make(__("erp.batteries.delivery_documents.{$kind}"))
                ->description($record->hasDocument($kind) ? __('erp.batteries.delivery_documents.current', ['name' => $record->documentName($kind)]) : __('erp.batteries.delivery_documents.none'))
                ->compact()
                ->schema([
                    FileUpload::make($kind)
                        ->hiddenLabel()
                        ->disk(BatteryDelivery::DISK)
                        ->directory('battery-deliveries/'.$record->id)
                        ->visibility('private')
                        ->acceptedFileTypes(self::TYPES)
                        ->maxSize(51200)
                        ->storeFileNamesIn($kind.'_name'),
                    Checkbox::make($kind.'_remove')
                        ->label(__('erp.batteries.delivery_documents.remove'))
                        ->visible($record->hasDocument($kind)),
                ]), BatteryDelivery::DOCUMENTS))
            ->action(function (BatteryDelivery $record, array $data): void {
                abort_unless(Gate::allows('manageDocuments', $record), 403);
                $changes = [];

                foreach (BatteryDelivery::DOCUMENTS as $kind) {
                    if (filled($data[$kind] ?? null)) {
                        $changes[$kind.'_document'] = $data[$kind];
                        $changes[$kind.'_document_name'] = $data[$kind.'_name'] ?? basename((string) $data[$kind]);
                    } elseif ($data[$kind.'_remove'] ?? false) {
                        $changes[$kind.'_document'] = null;
                        $changes[$kind.'_document_name'] = null;
                    }
                }

                $record->update($changes);
            })
            ->successNotificationTitle(__('erp.batteries.delivery_documents.saved'));
    }
}
