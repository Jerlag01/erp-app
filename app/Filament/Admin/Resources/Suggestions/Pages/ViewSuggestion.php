<?php

namespace App\Filament\Admin\Resources\Suggestions\Pages;

use App\Enums\SuggestionStatus;
use App\Filament\Admin\Resources\Suggestions\SuggestionResource;
use App\Models\Suggestion;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewSuggestion extends ViewRecord
{
    protected static string $resource = SuggestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label(__('erp.incidents.export_pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->url(fn (Suggestion $record): string => SuggestionResource::pdfUrl([$record->id]), shouldOpenInNewTab: true),
            Action::make('handle')
                ->label(__('erp.incidents.handle'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('gray')
                ->fillForm(fn (Suggestion $record): array => ['status' => $record->status->value, 'response' => $record->response])
                ->schema([
                    Select::make('status')
                        ->label(__('erp.fields.status'))
                        ->options(SuggestionStatus::class)
                        ->required(),
                    Textarea::make('response')
                        ->label(__('erp.suggestions.response'))
                        ->rows(5),
                ])
                ->action(function (Suggestion $record, array $data): void {
                    $record->update([
                        'status' => $data['status'],
                        'response' => $data['response'],
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
