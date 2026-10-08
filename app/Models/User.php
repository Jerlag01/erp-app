<?php

namespace App\Models;

use App\Enums\VacationStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['name', 'initials', 'email', 'password', 'role_id', 'job_title', 'phone', 'locale', 'is_active', 'vacation_days'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<int>|null */
    protected ?array $teamIdsCache = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'vacation_days' => 20,
        'is_active' => true,
        'locale' => 'nl',
    ];

    /**
     * Administrators may use both panels; other active users with a role only the employee side.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active || $this->role_id === null) {
            return false;
        }

        return $panel->getId() === 'admin' ? $this->isAdmin() : true;
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    /**
     * Vehicles this user is the regular driver of.
     *
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'driver_id');
    }

    /**
     * Assets handed out to this user personally.
     *
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * @return HasMany<VacationRequest, $this>
     */
    public function vacationRequests(): HasMany
    {
        return $this->hasMany(VacationRequest::class);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->role?->is_admin;
    }

    /**
     * Non-administrators who are in no team act as the guest / warehouse role, without any
     * permissions. Only applies while the teams module is on: without teams nobody has one.
     */
    public function isGuest(): bool
    {
        if ($this->isAdmin()) {
            return false;
        }

        if ($this->role?->is_guest) {
            return true;
        }

        return modules()->teams() && $this->teamIds() === [];
    }

    /**
     * The role that is in effect: the guest role while the user is in no team.
     */
    public function effectiveRole(): ?Role
    {
        return $this->isGuest() ? (Role::guest() ?? $this->role) : $this->role;
    }

    /**
     * Whether the user's role grants a permission such as "vehicles.update".
     * Guests have no permissions at all.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isGuest()) {
            return false;
        }

        return $this->role?->allows($permission) ?? false;
    }

    /**
     * Whether the user may see market prices and stock values (the distributors' special prices).
     */
    public function canSeePrices(): bool
    {
        return modules()->stock() && $this->hasPermission('stock_prices.view');
    }

    /**
     * Knowledge base editors also see hidden categories and unpublished articles.
     */
    public function canEditKnowledgeBase(): bool
    {
        return modules()->knowledgeBase()
            && ($this->hasPermission('knowledge_base.create') || $this->hasPermission('knowledge_base.update') || $this->hasPermission('knowledge_base.delete'));
    }

    /**
     * The employee record in the employee register, if any. Accounts can exist without one
     * (e.g. an administrator or a temporary account).
     *
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Whether visible records must be limited to the user's own teams.
     * Administrators see everything; without the teams module there is nothing to limit by.
     */
    public function seesOnlyOwnTeams(): bool
    {
        return ! $this->isAdmin() && modules()->teams();
    }

    /**
     * Ids of the teams the user belongs to, loaded once per request.
     *
     * @return list<int>
     */
    public function teamIds(): array
    {
        return $this->teamIdsCache ??= $this->teams()->pluck('teams.id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    public function flushTeamIds(): void
    {
        $this->teamIdsCache = null;
    }

    /**
     * The user and everyone who shares a team with them.
     *
     * @return list<int>
     */
    public function teamMemberIds(): array
    {
        if ($this->teamIds() === []) {
            return [$this->id];
        }

        return array_values(array_unique([$this->id, ...DB::table('team_user')->whereIn('team_id', $this->teamIds())->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all()]));
    }

    /**
     * Ids of the locations the user's teams are based at.
     *
     * @return list<int>
     */
    public function locationIds(): array
    {
        if ($this->teamIds() === []) {
            return [];
        }

        return Team::query()->whereKey($this->teamIds())->whereNotNull('location_id')->distinct()->pluck('location_id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Initials used in order references: the stored value, or the first letter
     * of the first and the last name ("Jeroen Lagaet" → "JL").
     */
    public function referenceInitials(): string
    {
        if (filled($this->initials)) {
            return strtoupper($this->initials);
        }

        $words = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: ['X'];
        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr(end($words), 0, 1) : mb_substr($words[0], 1, 1);

        return mb_strtoupper(Str::ascii($first.$last)) ?: 'XX';
    }

    /**
     * Vacation days used (approved) and reserved (pending) in a year, and what is left.
     *
     * @return array{allowance: float, approved: float, pending: float, remaining: float}
     */
    public function vacationBalance(?int $year = null, ?int $ignoreRequestId = null): array
    {
        $year ??= (int) date('Y');

        $sums = $this->vacationRequests()
            ->whereYear('start_date', $year)
            ->when($ignoreRequestId, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->whereIn('status', [VacationStatus::Approved, VacationStatus::Pending])
            ->selectRaw('status, SUM(days) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $allowance = (float) $this->vacation_days;
        $approved = (float) ($sums[VacationStatus::Approved->value] ?? 0);
        $pending = (float) ($sums[VacationStatus::Pending->value] ?? 0);

        return [
            'allowance' => $allowance,
            'approved' => $approved,
            'pending' => $pending,
            'remaining' => $allowance - $approved - $pending,
        ];
    }

    public function preferredLocale(): ?string
    {
        return array_key_exists((string) $this->locale, config('app.locales')) ? $this->locale : null;
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'vacation_days' => 'decimal:1',
        ];
    }
}
