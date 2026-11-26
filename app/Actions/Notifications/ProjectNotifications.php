<?php

namespace App\Actions\Notifications;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\BatteryDossier;
use App\Models\GpwRequest;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Notifications in the platform (the bell) about projects: a status change and an approved GPW request to
 * the project leaders, a finished battery dossier (the 604 is ready for invoicing) to the administrators.
 * Who caused it is not told about their own action.
 */
class ProjectNotifications
{
    public function statusChanged(Project $project): void
    {
        $project->loadMissing('leaders', 'category');

        foreach ($this->recipients($project->leaders) as $user) {
            $locale = $user->preferredLocale() ?? config('app.locale');

            Notification::make()
                ->title(__('erp.projects.notifications.status_title', ['project' => $project->reference], $locale))
                ->body(__('erp.projects.notifications.status_body', ['status' => __('erp.enums.project_status.'.$project->status->value, [], $locale)], $locale))
                ->icon(Heroicon::OutlinedArrowPath)
                ->info()
                ->actions([$this->open($project, $locale)])
                ->sendToDatabase($user);
        }
    }

    /**
     * Approved by the client: the project leaders (the requester is told by DecideGpw).
     */
    public function gpwApproved(GpwRequest $request): void
    {
        $request->loadMissing('project.leaders');
        $project = $request->project;

        if ($project === null) {
            return;
        }

        foreach ($this->recipients($project->leaders->reject(fn (User $leader): bool => $leader->id === $request->requested_by)) as $user) {
            $locale = $user->preferredLocale() ?? config('app.locale');

            Notification::make()
                ->title(__('erp.gpw.notifications.approved_title', ['project' => $project->reference], $locale))
                ->body(__('erp.gpw.notifications.body', ['by' => $request->decided_by_name, 'period' => $request->period()], $locale))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->success()
                ->actions([$this->open($project, $locale)])
                ->sendToDatabase($user);
        }
    }

    /**
     * The battery dossier of a 604 is finished: the project is ready for invoicing.
     */
    public function batteryFinished(BatteryDossier $dossier, ?User $by): void
    {
        $project = $dossier->project;

        if ($project === null) {
            return;
        }

        $admins = User::query()->active()->with('role')->get()->filter(fn (User $user): bool => $user->isAdmin());

        foreach ($this->recipients($admins, $by) as $user) {
            $locale = $user->preferredLocale() ?? config('app.locale');

            Notification::make()
                ->title(__('erp.batteries.notifications.finished_title', ['project' => $project->reference], $locale))
                ->body(__('erp.batteries.notifications.finished_body', ['name' => $by?->name ?? '—'], $locale))
                ->icon(Heroicon::OutlinedBattery100)
                ->success()
                ->actions([$this->open($project, $locale)])
                ->sendToDatabase($user);
        }
    }

    /**
     * Active users, without the one who did it.
     *
     * @param  iterable<User>  $users
     * @return Collection<int, User>
     */
    protected function recipients(iterable $users, ?User $by = null): Collection
    {
        $actor = $by?->id ?? auth()->id();

        return collect($users)->filter(fn (User $user): bool => $user->is_active && $user->id !== $actor)->unique('id')->values();
    }

    protected function open(Project $project, string $locale): Action
    {
        return Action::make('open')
            ->label(__('erp.projects.notifications.open', [], $locale))
            ->url(ProjectResource::getUrl('view', ['record' => $project], panel: 'app'));
    }
}
