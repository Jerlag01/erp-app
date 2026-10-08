<?php

namespace App\Filament\Admin\Resources\Incidents\Pages;

use App\Enums\IncidentStatus;
use App\Filament\Admin\Resources\Incidents\IncidentResource;
use App\Models\Incident;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewIncident extends ViewRecord
{
    protected static string $resource = IncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label(__('erp.incidents.export_pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->url(fn (Incident $record): string => IncidentResource::pdfUrl([$record->id]), shouldOpenInNewTab: true),
            Action::make('handle')
                ->label(__('erp.incidents.handle'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('gray')
                ->fillForm(fn (Incident $record): array => ['status' => $record->status->value, 'follow_up' => $record->follow_up])
                ->schema([
                    Select::make('status')
                        ->label(__('erp.fields.status'))
                        ->options(IncidentStatus::class)
                        ->required(),
                    Textarea::make('follow_up')
                        ->label(__('erp.incidents.follow_up'))
                        ->placeholder(__('erp.incidents.follow_up_placeholder'))
                        ->rows(5),
                ])
                ->action(function (Incident $record, array $data): void {
                    $record->update([
                        'status' => $data['status'],
                        'follow_up' => $data['follow_up'],
                        'handled_by' => auth()->id(),
                        'handled_at' => now(),
                    ]);
                    $record->refresh();
                })
                ->successNotificationTitle(__('erp.incidents.saved')),
            DeleteAction::make(),
        ];
    }
}
