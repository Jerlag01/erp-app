<?php

namespace App\Models;

use App\Enums\GpwStatus;
use Database\Factories\GpwRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A GPW request for a project: the period we ask the COW site's contact person to work in, with
 * remarks. The client approves or refuses it (mail link or client portal); once approved, the
 * requester plans a team in that period.
 */
#[Fillable([
    'project_id', 'requested_by', 'work_site_contact_id', 'contact_name', 'contact_email', 'locale', 'starts_on', 'ends_on', 'remarks',
    'status', 'mailed_at', 'decided_at', 'decided_by_client_user_id', 'decided_by_name', 'decision_note', 'team_id', 'planned_at', 'planned_by',
])]
class GpwRequest extends Model
{
    /** @use HasFactory<GpwRequestFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<WorkSiteContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WorkSiteContact::class, 'work_site_contact_id');
    }

    /**
     * @return BelongsTo<ClientUser, $this>
     */
    public function decidedByClientUser(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'decided_by_client_user_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isPending(): bool
    {
        return $this->status === GpwStatus::Pending;
    }

    /**
     * "12/11/2026 – 14/11/2026", or one date for a single day.
     */
    public function period(): string
    {
        $from = $this->starts_on->format('d/m/Y');

        return $this->starts_on->equalTo($this->ends_on) ? $from : $from.' – '.$this->ends_on->format('d/m/Y');
    }

    /**
     * Whether the user may plan this approved request: the requester, or someone who may plan the project anyway.
     */
    public function canBePlannedBy(User $user): bool
    {
        return in_array($this->status, [GpwStatus::Approved, GpwStatus::Planned], true)
            && $this->project !== null
            && ($this->requested_by === $user->id || $user->isAdmin() || $user->hasPermission('planning.plan') || $this->project->isLedBy($user));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GpwStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'mailed_at' => 'datetime',
            'decided_at' => 'datetime',
            'planned_at' => 'datetime',
        ];
    }
}
