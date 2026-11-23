<?php

namespace App\Models;

use App\Enums\GpwStatus;
use Carbon\CarbonInterface;
use Database\Factories\GpwRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A GPW request for a project: the period we ask the COW site's contact person to work in, with
 * remarks. The client approves or refuses it (mail link or client portal); once approved, the
 * requester plans a team in that period. A request can cover several COW codes of the project (an
 * intervention's COW codes, a battery move's origin and destination), each with its own period
 * (GpwRequestSite); starts_on/ends_on then span all of them, and one approval covers them all. Several
 * projects can be asked in one go (so the client gets one mail): each keeps its own request and period,
 * they share a batch_id, are mailed once and decided together from that mail (separately in the portal).
 */
#[Fillable([
    'project_id', 'batch_id', 'requested_by', 'contact_user_id', 'work_site_contact_id', 'contact_ids', 'employee_ids', 'contact_name', 'contact_email', 'locale', 'starts_on', 'ends_on', 'remarks',
    'status', 'source', 'mailed_at', 'decided_at', 'decided_by_client_user_id', 'decided_by_name', 'decision_note', 'team_id', 'planned_at', 'planned_by',
    'status_locked_at', 'status_locked_by',
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
     * The project leader who is the contact person in the mail (else the requester).
     *
     * @return BelongsTo<User, $this>
     */
    public function contactUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    /**
     * Our contact person for the client: the chosen project leader, or else the requester.
     */
    public function contactPerson(): ?User
    {
        return $this->contactUser ?? $this->requester;
    }

    /**
     * The employees who will do the work, in the order chosen.
     *
     * @return Collection<int, User>
     */
    public function employees(): Collection
    {
        $ids = array_map('intval', (array) ($this->employee_ids ?? []));

        if ($ids === []) {
            return collect();
        }

        $users = User::query()->whereKey($ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id): ?User => $users->get($id))->filter()->values();
    }

    /**
     * "Jan Peeters, Ann Smet", or null when none were chosen.
     */
    public function employeeNames(): ?string
    {
        $names = $this->employees()->pluck('name');

        return $names->isNotEmpty() ? $names->implode(', ') : null;
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

    /**
     * The COW codes this request covers with their periods, when it was asked for several (else none).
     *
     * @return HasMany<GpwRequestSite, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(GpwRequestSite::class)->orderBy('starts_on')->orderBy('id');
    }

    /**
     * The requests asked in the same mail (this one included), the first asked first; just this one
     * when it was asked alone.
     *
     * @return Collection<int, GpwRequest>
     */
    public function batch(): Collection
    {
        if ($this->batch_id === null) {
            return collect([$this]);
        }

        return self::query()->where('batch_id', $this->batch_id)->with(['project.workSite', 'sites.workSite'])->orderBy('id')->get()->toBase();
    }

    /**
     * The requests of the mail still waiting for the client.
     *
     * @return Collection<int, GpwRequest>
     */
    public function pendingBatch(): Collection
    {
        return $this->batch()->filter(fn (GpwRequest $request): bool => $request->isPending())->values();
    }

    public function isBatched(): bool
    {
        return $this->batch_id !== null;
    }

    public const SOURCE_MAIL = 'mail';

    public const SOURCE_MANUAL = 'manual';

    public function isManual(): bool
    {
        return $this->source === self::SOURCE_MANUAL;
    }

    /**
     * Where the request stands for the overview: "Waiting for GPW approval", "To be planned - GPW
     * approved", "On hold - resend GPW request", or the status itself.
     */
    public function overviewStatus(): string
    {
        return __('erp.gpw.overview_status.'.$this->status->value);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function statusLockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_locked_by');
    }

    /**
     * Set by an administrator: no longer follows the project (see SyncGpwStatus).
     */
    public function isLocked(): bool
    {
        return $this->status_locked_at !== null;
    }

    public function isPending(): bool
    {
        return $this->status === GpwStatus::Pending;
    }

    /**
     * "12/11/2026 – 14/11/2026", or one date for a single day. For a request with COW codes on different
     * dates: each COW code with its own period, "02GAM: 12/11/2026 · 03ABC: 13/11/2026 – 14/11/2026".
     */
    public function period(): string
    {
        $sites = $this->sites;

        if ($sites->count() > 1 && $sites->contains(fn (GpwRequestSite $site): bool => ! $site->starts_on->equalTo($this->starts_on) || ! $site->ends_on->equalTo($this->ends_on))) {
            return $sites->map(fn (GpwRequestSite $site): string => ($site->cow_code ?? '—').': '.$site->period())->implode(' · ');
        }

        return $this->overallPeriod();
    }

    /**
     * From the first to the last day asked for, over all COW codes.
     */
    public function overallPeriod(): string
    {
        return self::formatPeriod($this->starts_on, $this->ends_on);
    }

    public static function formatPeriod(CarbonInterface $from, CarbonInterface $until): string
    {
        return $from->equalTo($until) ? $from->format('d/m/Y') : $from->format('d/m/Y').' – '.$until->format('d/m/Y');
    }

    /**
     * The COW codes asked for: "02GAM, 03ABC", or the project's.
     */
    public function cowCodes(): ?string
    {
        $codes = $this->sites->pluck('cow_code')->filter()->unique()->values();

        return $codes->isNotEmpty() ? $codes->implode(', ') : ($this->project?->workSite?->cow_code ?? $this->project?->cow_code);
    }

    /**
     * Whether the user may plan this approved request: the requester, or someone who may plan the project anyway.
     */
    public function canBePlannedBy(User $user): bool
    {
        return in_array($this->status, [GpwStatus::Approved, GpwStatus::Planned, GpwStatus::InProgress], true)
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
            'contact_ids' => 'array',
            'employee_ids' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'mailed_at' => 'datetime',
            'decided_at' => 'datetime',
            'planned_at' => 'datetime',
            'status_locked_at' => 'datetime',
        ];
    }
}
