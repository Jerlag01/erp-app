<?php

namespace App\Actions\Gpw;

use App\Enums\GpwStatus;
use App\Enums\MailTemplateKey;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Mail\SystemMail;
use App\Models\ClientUser;
use App\Models\GpwRequest;
use App\Support\PushNotifications;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The client approves or refuses a pending GPW request (from the mail link or the client portal).
 * Approved: the project becomes "to be planned", in the approved period. Either way the requester
 * is told in the ERP, on their phone and by e-mail, in their own language.
 */
class DecideGpw
{
    public function approve(GpwRequest $request, string $decidedBy, ?ClientUser $clientUser = null, ?string $note = null): GpwRequest
    {
        return $this->decide($request, GpwStatus::Approved, $decidedBy, $clientUser, $note);
    }

    public function reject(GpwRequest $request, string $decidedBy, ?ClientUser $clientUser = null, ?string $note = null): GpwRequest
    {
        return $this->decide($request, GpwStatus::Rejected, $decidedBy, $clientUser, $note);
    }

    protected function decide(GpwRequest $request, GpwStatus $status, string $decidedBy, ?ClientUser $clientUser, ?string $note): GpwRequest
    {
        DB::transaction(function () use ($request, $status, $decidedBy, $clientUser, $note): void {
            $request = GpwRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $request->isPending()) {
                throw ValidationException::withMessages(['status' => __('erp.gpw.errors.already_decided')]);
            }

            $request->update([
                'status' => $status,
                'decided_at' => now(),
                'decided_by_client_user_id' => $clientUser?->id,
                'decided_by_name' => mb_substr($decidedBy, 0, 150),
                'decision_note' => filled($note) ? trim($note) : null,
            ]);

            $project = $request->project;

            if ($status === GpwStatus::Approved && $project !== null && in_array($project->status, [ProjectStatus::ToBeExecuted, ProjectStatus::ToBePlanned], true)) {
                $project->update(['status' => ProjectStatus::ToBePlanned]);
            }
        });

        $request->refresh();
        $this->notifyRequester($request);

        return $request;
    }

    protected function notifyRequester(GpwRequest $request): void
    {
        $request->loadMissing('requester', 'project.workSite');
        $requester = $request->requester;
        $project = $request->project;

        if ($requester === null || $project === null || ! $requester->is_active) {
            return;
        }

        $approved = $request->status === GpwStatus::Approved;
        $locale = $requester->preferredLocale() ?? config('app.locale');
        $url = ProjectResource::getUrl('view', ['record' => $project], panel: 'app');
        $title = __($approved ? 'erp.gpw.notifications.approved_title' : 'erp.gpw.notifications.rejected_title', ['project' => $project->reference], $locale);
        $body = __('erp.gpw.notifications.body', ['by' => $request->decided_by_name, 'period' => $request->period()], $locale);

        Notification::make()
            ->title($title)
            ->body(e($body).(filled($request->decision_note) ? '<br>'.e($request->decision_note) : ''))
            ->icon($approved ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->{$approved ? 'success' : 'danger'}()
            ->actions([Action::make('open')->label(__('erp.gpw.notifications.open', [], $locale))->url($url)])
            ->sendToDatabase($requester);

        app(PushNotifications::class)->toUser($requester, $title, $body, ['screen' => 'planning']);

        try {
            Mail::to($requester->email, $requester->name)->send(new SystemMail($approved ? MailTemplateKey::GpwApproved : MailTemplateKey::GpwRejected, $locale, [
                'name' => $requester->name,
                'project' => $project->reference,
                'cow_code' => $project->workSite?->cow_code ?? '—',
                'period' => $request->period(),
                'decided_by' => $request->decided_by_name,
                'note' => $request->decision_note ?? '—',
                'project_url' => $url,
            ]));
        } catch (Throwable $exception) {
            Log::warning('GPW decision could not be mailed', ['gpw_request' => $request->id, 'error' => $exception->getMessage()]);
        }
    }
}
