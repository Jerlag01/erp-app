<?php

namespace App\Actions;

use App\Enums\MailTemplateKey;
use App\Enums\SuggestionStatus;
use App\Filament\Admin\Resources\Suggestions\SuggestionResource;
use App\Filament\App\Pages\MySuggestions;
use App\Mail\SystemMail;
use App\Models\Suggestion;
use App\Models\SuggestionResponse;
use App\Models\User;
use App\Support\PrivatePhotos;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Adds a reply to the conversation on an idea or complaint. A reply of an administrator goes to the
 * employee who submitted it; a reply of that employee goes to the administrators. Each recipient is
 * told in the platform and by e-mail, in their own language. An administrator's first reply puts a
 * new entry "in progress".
 */
class RespondToSuggestion
{
    /** Whether every e-mail of the last reply could be sent. */
    public bool $mailFailed = false;

    /**
     * @param  array{body: string, photos?: array<int, string>|null}  $data
     */
    public function handle(Suggestion $suggestion, User $author, array $data): SuggestionResponse
    {
        abort_unless(Gate::forUser($author)->allows('respond', $suggestion), 403);

        $fromAdmin = $author->isAdmin() && $suggestion->user_id !== $author->id;

        $response = $suggestion->responses()->create([
            'user_id' => $author->id,
            'author_name' => $author->name,
            'from_admin' => $fromAdmin,
            'body' => trim($data['body']),
            'photos' => array_values(array_filter((array) ($data['photos'] ?? []))),
        ]);

        if ($fromAdmin) {
            $suggestion->update([
                'status' => $suggestion->status === SuggestionStatus::New ? SuggestionStatus::InProgress : $suggestion->status,
                'handled_by' => $author->id,
                'handled_at' => now(),
            ]);
        }

        $this->mailFailed = false;

        foreach ($this->recipients($suggestion, $author, $fromAdmin) as $recipient) {
            $this->notify($recipient, $suggestion, $response, ! $fromAdmin);
        }

        return $response;
    }

    /**
     * The reply form: text and photos, the same on both sides.
     *
     * @return list<Component>
     */
    public static function schema(): array
    {
        return [
            Textarea::make('body')
                ->label(__('erp.suggestions.responses.message'))
                ->placeholder(__('erp.suggestions.responses.message_placeholder'))
                ->rows(5)
                ->required()
                ->maxLength(10000),
            PrivatePhotos::upload('photos', 'suggestions/replies')
                ->label(__('erp.incidents.photos'))
                ->helperText(__('erp.incidents.photos_help')),
        ];
    }

    /**
     * @return list<User>
     */
    protected function recipients(Suggestion $suggestion, User $author, bool $fromAdmin): array
    {
        if ($fromAdmin) {
            $submitter = $suggestion->user;

            return $submitter && $submitter->is_active ? [$submitter] : [];
        }

        return User::query()->active()
            ->whereHas('role', fn ($query) => $query->where('is_admin', true))
            ->whereKeyNot($author->id)
            ->get()
            ->all();
    }

    /**
     * $toAdmin: the recipient reads it on the administration side, otherwise on "My submissions".
     */
    protected function notify(User $recipient, Suggestion $suggestion, SuggestionResponse $response, bool $toAdmin): void
    {
        $locale = $recipient->preferredLocale() ?? config('app.locale');
        $type = __('erp.enums.suggestion_type.'.$suggestion->type->value, [], $locale);
        $url = $toAdmin
            ? SuggestionResource::getUrl('view', ['record' => $suggestion], panel: 'admin')
            : MySuggestions::getUrl(['suggestion' => $suggestion->id], panel: 'app');

        Notification::make()
            ->title(__('erp.suggestions.responses.notification_title', ['name' => $response->author_name, 'type' => mb_strtolower($type)], $locale))
            ->body(str($response->body)->limit(160)->toString())
            ->icon('heroicon-o-chat-bubble-left-right')
            ->info()
            ->actions([Action::make('open')->label(__('erp.suggestions.responses.open', [], $locale))->url($url)])
            ->sendToDatabase($recipient);

        try {
            Mail::to($recipient->email, $recipient->name)->send(new SystemMail(MailTemplateKey::SuggestionResponse, $locale, [
                'name' => $recipient->name,
                'author' => $response->author_name,
                'type' => mb_strtolower($type),
                'submitted_on' => $suggestion->submitted_at->format('d/m/Y'),
                'message' => $response->body,
                'url' => $url,
            ]));
        } catch (Throwable $exception) {
            $this->mailFailed = true;
            Log::warning('Idea box reply could not be mailed', ['suggestion' => $suggestion->id, 'user' => $recipient->id, 'error' => $exception->getMessage()]);
        }
    }
}
