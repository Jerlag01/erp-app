<?php

namespace App\Actions\Gpw;

use App\Enums\GpwStatus;
use App\Enums\ProjectStatus;
use App\Models\GpwRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * A GPW request that was asked through a personal mail, added by hand with where it stands: waiting,
 * approved or refused. No mail goes out. Approved, it does what a client's approval does: the project
 * becomes "to be planned".
 */
class AddManualGpw
{
    /**
     * @param  array{project_id: int|string, starts_on: string, ends_on: string, status: string, contact_name: string, contact_email?: string|null, decided_by_name?: string|null, decision_note?: string|null, remarks?: string|null}  $data
     */
    public function handle(array $data, User $actor): GpwRequest
    {
        $project = Project::query()->visibleTo($actor)->find($data['project_id']);
        abort_unless($project !== null && Gate::forUser($actor)->allows('manageGpw', $project), 403);

        $status = GpwStatus::tryFrom((string) $data['status']);

        if (! in_array($status, [GpwStatus::Pending, GpwStatus::Approved, GpwStatus::Rejected], true)) {
            throw ValidationException::withMessages(['status' => __('validation.in', ['attribute' => __('erp.fields.status')])]);
        }

        $from = Carbon::parse($data['starts_on'])->startOfDay();
        $until = Carbon::parse($data['ends_on'])->startOfDay();

        if ($until->isBefore($from)) {
            throw ValidationException::withMessages(['ends_on' => __('validation.after_or_equal', ['attribute' => __('erp.gpw.ends_on'), 'date' => $from->format('d/m/Y')])]);
        }

        $decided = $status !== GpwStatus::Pending;

        return DB::transaction(function () use ($data, $actor, $project, $status, $from, $until, $decided): GpwRequest {
            $request = GpwRequest::query()->create([
                'project_id' => $project->id,
                'requested_by' => $actor->id,
                'contact_name' => mb_substr(trim((string) $data['contact_name']), 0, 150),
                'contact_email' => filled($data['contact_email'] ?? null) ? mb_substr(trim((string) $data['contact_email']), 0, 150) : null,
                'locale' => $actor->preferredLocale() ?? (string) config('app.locale'),
                'starts_on' => $from->toDateString(),
                'ends_on' => $until->toDateString(),
                'remarks' => filled($data['remarks'] ?? null) ? trim((string) $data['remarks']) : null,
                'status' => $status,
                'source' => GpwRequest::SOURCE_MANUAL,
                'decided_at' => $decided ? now() : null,
                'decided_by_name' => $decided ? mb_substr(trim((string) ($data['decided_by_name'] ?? '')) ?: (string) $data['contact_name'], 0, 150) : null,
                'decision_note' => $decided && filled($data['decision_note'] ?? null) ? trim((string) $data['decision_note']) : null,
            ]);

            if ($status === GpwStatus::Approved && in_array($project->status, [ProjectStatus::ToBeExecuted, ProjectStatus::ToBePlanned], true)) {
                $project->update(['status' => ProjectStatus::ToBePlanned]);
            }

            return $request;
        });
    }
}
