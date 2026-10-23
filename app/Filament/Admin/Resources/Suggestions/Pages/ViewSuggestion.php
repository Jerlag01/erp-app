<?php

namespace App\Filament\Admin\Resources\Suggestions\Pages;

use App\Actions\RespondToSuggestion;
use App\Enums\SuggestionStatus;
use App\Filament\Admin\Resources\Suggestions\SuggestionResource;
use App\Models\Suggestion;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

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
            Action::make('respond')
                ->label(__('erp.suggestions.responses.respond'))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->modalHeading(__('erp.suggestions.responses.respond'))
                ->modalDescription(fn (Suggestion $record): string => __('erp.suggestions.responses.respond_help', ['name' => $record->submitter_name ?: '—']))
                ->visible(fn (Suggestion $record): bool => Gate::allows('respond', $record))
                ->schema(RespondToSuggestion::schema())
                ->action(function (Suggestion $record, array $data, RespondToSuggestion $respond): void {
                    $respond->handle($record, auth()->user(), $data);
                    $record->refresh();

                    $respond->mailFailed
                        ? Notification::make()->title(__('erp.suggestions.responses.sent'))->body(__('erp.suggestions.responses.mail_failed'))->warning()->send()
                        : Notification::make()->title(__('erp.suggestions.responses.sent'))->success()->send();
                }),
            Action::make('handle')
                ->label(__('erp.incidents.handle'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('gray')
                // The status only: answers are given as replies in the conversation.
                ->fillForm(fn (Suggestion $record): array => ['status' => $record->status->value])
                ->schema([
                    Select::make('status')
                        ->label(__('erp.fields.status'))
                        ->options(SuggestionStatus::class)
                        ->required(),
                ])
                ->action(function (Suggestion $record, array $data): void {
                    $record->update([
                        'status' => $data['status'],
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
