<?php

namespace App\Actions;

use App\Filament\Resources\VacationRequests\VacationRequestResource;
use App\Models\User;
use App\Models\VacationRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sends an in-app notification about a new request to everyone who may approve it:
 * administrators, and team members of the requester with the approve permission.
 */
class NotifyVacationApprovers
{
    public function handle(VacationRequest $request): void
    {
        $approvers = $this->approversFor($request->user);

        if ($approvers->isEmpty()) {
            return;
        }

        foreach ($approvers as $approver) {
            Notification::make()
                ->title(__('erp.vacations.notifications.new_title', ['name' => $request->user->name], $approver->preferredLocale()))
                ->body(__('erp.vacations.notifications.new_body', [
                    'start' => $request->start_date->format('d/m/Y'),
                    'end' => $request->end_date->format('d/m/Y'),
                    'days' => $request->days,
                ], $approver->preferredLocale()))
                ->icon('heroicon-o-sun')
                ->warning()
                ->actions([
                    Action::make('review')
                        ->label(__('erp.vacations.review', locale: $approver->preferredLocale()))
                        ->url(VacationRequestResource::getUrl(panel: 'app')),
                ])
                ->sendToDatabase($approver);
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function approversFor(User $requester): Collection
    {
        $teamIds = $requester->teamIds();

        return User::query()
            ->active()
            ->whereKeyNot($requester->id)
            ->with(['role', 'teams'])
            ->get()
            ->filter(fn (User $user): bool => $user->isAdmin() || (
                $user->hasPermission('vacations.approve')
                && $user->teams->pluck('id')->intersect($teamIds)->isNotEmpty()
            ))
            ->values();
    }
}
