<?php

namespace App\Models;

use App\Enums\MobileKeyRevokeReason;
use App\Enums\VacationStatus;
use App\Models\Concerns\HasTwoFactorAuthentication;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
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

#[Fillable(['name', 'initials', 'email', 'password', 'role_id', 'permissions', 'job_title', 'phone', 'locale', 'is_active', 'vacation_days', 'mobile_app_dismissed_release'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTwoFactorAuthentication, Notifiable;

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
     * The messages sent to the user's mobile app from the platform.
     *
     * @return BelongsToMany<PushMessage, $this>
     */
    public function pushMessages(): BelongsToMany
    {
        return $this->belongsToMany(PushMessage::class)->withPivot(['locale', 'read_at'])->withTimestamps();
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

    /**
     * Colleagues this user shared their planning with (on top of their team members).
     *
     * @return BelongsToMany<User, $this>
     */
    public function planningViewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'planning_shares', 'user_id', 'viewer_id')->withTimestamps();
    }

    /**
     * @return HasMany<PlanningItem, $this>
     */
    public function planningItems(): HasMany
    {
        return $this->hasMany(PlanningItem::class);
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

        // Office roles without a prestation sheet (e.g. HR) work without a team.
        if ($this->role?->no_timesheet) {
            return false;
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
     * Whether the user has a permission such as "vehicles.update":
     * - an administrator role grants everything except the explicit permissions (Permissions::EXPLICIT);
     * - otherwise the user's own permissions for that area overrule the role's (also to take rights away);
     * - otherwise the role's; guests (no team) have none.
     */
    public function hasPermission(string $permission): bool
    {
        // Given to this user alone: never through the role, not even for administrators.
        if (Permissions::isUserOnly($permission)) {
            [$area, $ability] = explode('.', $permission, 2);

            return in_array($ability, (array) ($this->permissions[$area] ?? []), true);
        }

        if ($this->isAdmin() && ! Permissions::isExplicit($permission)) {
            return true;
        }

        [$area, $ability] = array_pad(explode('.', $permission, 2), 2, null);
        $own = $this->permissions;

        if (is_array($own) && array_key_exists($area, $own)) {
            return in_array($ability, (array) $own[$area], true);
        }

        if ($this->isGuest()) {
            return false;
        }

        return $this->role?->allows($permission) ?? false;
    }

    /**
     * Whether the user has permissions of their own (that differ from their role).
     */
    public function hasOwnPermissions(): bool
    {
        return is_array($this->permissions) && $this->permissions !== [];
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
     * Keys of the mobile app, one per device the user signed in on.
     *
     * @return HasMany<MobileKey, $this>
     */
    public function mobileKeys(): HasMany
    {
        return $this->hasMany(MobileKey::class);
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
            ->vacation()
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
     * The first name: from the employee register, else the first word of the account name.
     */
    public function firstName(): string
    {
        return (string) ($this->employee?->first_name ?: strtok((string) $this->name, ' '));
    }

    /**
     * Manages all leave and absences of everyone: administrators, and those with "vacations.manage"
     * (e.g. the role Administratie) — also without a team.
     */
    public function managesLeave(): bool
    {
        return modules()->vacations() && ($this->isAdmin() || $this->hasPermission('vacations.manage'));
    }

    /**
     * Whether the user has a prestation sheet: not with a role such as HR ("no timesheet"), which also
     * keeps them out of the planning, vacation and sickness.
     */
    public function hasTimesheet(): bool
    {
        return ! $this->role?->no_timesheet;
    }

    /**
     * Users with a prestation sheet (their role is not "no timesheet").
     *
     * @param  Builder<User>  $query
     */
    public function scopeWithTimesheet(Builder $query): void
    {
        $query->whereDoesntHave('role', fn (Builder $query) => $query->where('no_timesheet', true));
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Only known permissions are stored, whatever the form sends.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->isDirty('permissions')) {
                $user->permissions = is_array($user->permissions) && $user->permissions !== [] ? Permissions::sanitizeOverrides($user->permissions) : null;
            }
        });

        // A login that is switched off (e.g. the employee left) loses its mobile app keys right away.
        static::saved(function (self $user): void {
            if ($user->wasChanged('is_active') && ! $user->is_active) {
                MobileKey::revokeAllFor($user, MobileKeyRevokeReason::UserInactive);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    /**
     * @return HasMany<OnboardingSignature, $this>
     */
    public function onboardingSignatures(): HasMany
    {
        return $this->hasMany(OnboardingSignature::class);
    }

    protected function casts(): array
    {
        return [
            'onboarding_completed_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'welcome_sent_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'vacation_days' => 'decimal:1',
            'permissions' => 'array',
            'dashboard' => 'array',
        ];
    }
}
