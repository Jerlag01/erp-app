<?php

namespace App\Actions\Toolbox;

use App\Filament\Resources\ToolboxMeetings\ToolboxMeetingResource;
use App\Models\ToolboxMeeting;
use App\Models\User;
use App\Support\PushNotifications;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Publishes a toolbox meeting: from then on every employee sees it, and everyone who has to sign it is
 * told in the platform and on their phone.
 */
class PublishToolboxMeeting
{
    public function handle(ToolboxMeeting $meeting, ?User $by = null): void
    {
        if ($meeting->isPublished()) {
            return;
        }

        $meeting->update(['published_at' => now()]);

        foreach (ToolboxMeeting::expectedUsers() as $user) {
            if ($user->id === $by?->id) {
                continue;
            }

            $locale = $user->preferredLocale() ?? config('app.locale');
            $title = __('erp.toolbox.notification_title', ['title' => $meeting->title], $locale);
            $body = __('erp.toolbox.notification_body', ['month' => $meeting->monthLabel($locale)], $locale);

            Notification::make()
                ->title($title)
                ->body($body)
                ->icon('heroicon-o-presentation-chart-line')
                ->warning()
                ->actions([
                    Action::make('open')
                        ->label(__('erp.toolbox.open', locale: $locale))
                        ->url(ToolboxMeetingResource::getUrl('view', ['record' => $meeting], panel: 'app')),
                ])
                ->sendToDatabase($user);

            app(PushNotifications::class)->toUser($user, $title, $body, ['screen' => 'toolbox', 'id' => $meeting->id]);
        }
    }
}
