<?php

namespace App\Support\Notifications;

use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Filament's notification, but the bell's notifications are stored at once instead of being queued:
 * Filament queues them, and without a queue worker for the ERP they never reached the bell (they waited
 * in the jobs table). Bound in place of Filament's class in AppServiceProvider, so every
 * Notification::make()->sendToDatabase() in the app uses it.
 */
class ErpNotification extends Notification
{
    /**
     * @param  Model | Authenticatable | Collection | array<Model | Authenticatable>  $users
     */
    public function sendToDatabase(Model|Authenticatable|Collection|array $users, bool $isEventDispatched = false): static
    {
        if (! is_iterable($users)) {
            $users = [$users];
        }

        foreach ($users as $user) {
            $user->notifyNow($this->toDatabase());

            if ($isEventDispatched) {
                DatabaseNotificationsSent::dispatch($user);
            }
        }

        return $this;
    }
}
