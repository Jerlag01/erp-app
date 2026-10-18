<?php

namespace App\Filament\Resources\ToolboxMeetings\Pages;

use App\Actions\Toolbox\PublishToolboxMeeting;
use App\Filament\Forms\SignaturePad;
use App\Filament\Resources\ToolboxMeetings\ToolboxMeetingResource;
use App\Models\ToolboxMeeting;
use App\Models\ToolboxMeetingParticipant;
use App\Models\User;
use App\Policies\ToolboxMeetingPolicy;
use App\Support\PdfAccess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reading a toolbox meeting. Signing becomes possible once the employee has read everything: scrolled
 * to the end of the text (when there is text) and downloaded every attachment (when there are any).
 * Their name and written signature are then kept. Who may see the signatures gets the list below.
 *
 * @property ToolboxMeeting $record
 */
class ViewToolboxMeeting extends ViewRecord
{
    protected static string $resource = ToolboxMeetingResource::class;

    protected string $view = 'filament.resources.toolbox-meetings.view';

    public function getTitle(): string
    {
        return $this->record->title;
    }

    public function getSubheading(): ?string
    {
        return $this->record->monthLabel(app()->getLocale());
    }

    /**
     * The signed-in user's reading progress; only for published meetings.
     */
    public function participant(): ?ToolboxMeetingParticipant
    {
        return $this->record->isPublished() ? $this->record->participantFor($this->user()) : null;
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * Called when the end of the text comes into view.
     */
    public function markTextRead(): void
    {
        $this->participant()?->recordTextRead();
    }

    /**
     * Downloads an attachment and notes that the user opened it.
     */
    public function download(int $index): ?StreamedResponse
    {
        abort_unless(Gate::allows('view', $this->record), 403);

        $file = $this->record->attachmentList()[$index] ?? null;
        $disk = Storage::disk(ToolboxMeeting::FILE_DISK);
        abort_if($file === null || ! $disk->exists($file['path']), 404);

        $this->participant()?->recordDownload($file['path']);

        return $disk->download($file['path'], $file['name']);
    }

    /**
     * The attendance list for who may see the signatures: every attendee with their row (if any).
     *
     * @return Collection<int, array{user: User, participant: ?ToolboxMeetingParticipant}>
     */
    public function attendance(): Collection
    {
        $rows = $this->record->participants()->get()->keyBy('user_id');

        return $this->record->attendees()->map(fn (User $user): array => ['user' => $user, 'participant' => $rows->get($user->id)]);
    }

    public function seesSignatures(): bool
    {
        return ToolboxMeetingPolicy::seesSignatures($this->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sign')
                ->label(__('erp.toolbox.sign'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('primary')
                ->visible(fn (): bool => $this->record->isPublished() && ! $this->participant()?->isSigned() && Gate::allows('sign', $this->record))
                ->disabled(fn (): bool => ! $this->participant()?->hasReadEverything())
                ->tooltip(fn (): ?string => $this->participant()?->hasReadEverything() ? null : __('erp.toolbox.read_first'))
                ->modalHeading(__('erp.toolbox.sign_heading', ['title' => $this->record->title]))
                ->modalDescription(__('erp.toolbox.sign_help'))
                ->schema([
                    TextInput::make('signer_name')
                        ->label(__('erp.batteries.signature.name'))
                        ->default(fn (): string => $this->user()->name)
                        ->required()
                        ->maxLength(150),
                    SignaturePad::make('signature')->label(__('erp.batteries.signature.title'))->required(),
                ])
                ->action(function (array $data): void {
                    $participant = $this->participant();
                    abort_unless($participant !== null && Gate::allows('sign', $this->record) && $participant->hasReadEverything() && ! $participant->isSigned(), 403);

                    $participant->sign($data['signer_name'], $data['signature']);

                    Notification::make()->title(__('erp.toolbox.signed_notice'))->success()->send();
                }),
            self::publishAction(),
            Action::make('pdf')
                ->label(__('erp.toolbox.pdf'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->visible(fn (): bool => $this->seesSignatures() && PdfAccess::canGenerate($this->user(), 'toolbox'))
                ->url(fn (): string => route('toolbox.pdf', $this->record), shouldOpenInNewTab: true),
            EditAction::make(),
        ];
    }

    public static function publishAction(): Action
    {
        return Action::make('publish')
            ->label(__('erp.toolbox.publish'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->visible(fn (ToolboxMeeting $record): bool => ! $record->isPublished() && Gate::allows('update', $record))
            ->requiresConfirmation()
            ->modalDescription(__('erp.toolbox.publish_help'))
            ->action(function (ToolboxMeeting $record): void {
                abort_unless(Gate::allows('update', $record), 403);
                app(PublishToolboxMeeting::class)->handle($record, auth()->user());

                Notification::make()->title(__('erp.toolbox.published_notice'))->success()->send();
            });
    }
}
