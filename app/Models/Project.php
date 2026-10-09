<?php

namespace App\Models;

use App\Enums\ProjectFileSection;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * A project in a category, e.g. 604-001-02GAM. Only administrators, its project leaders and the
 * teams assigned to it can see it (while the teams module is on).
 */
#[Fillable([
    'project_category_id', 'number', 'work_order_number', 'work_site_id', 'short_description', 'status',
    'intervention_subcategory_id', 'intervention_phase', 'intervention_tags',
    'client_name', 'client_company', 'client_email', 'client_phone',
    'street', 'house_number', 'addition', 'postal_code', 'city', 'country_id', 'notes', 'created_by',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ProjectCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    /**
     * @return BelongsTo<WorkSite, $this>
     */
    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function leaders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_leaders');
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    /**
     * @return HasMany<ProjectPoNumber, $this>
     */
    public function poNumbers(): HasMany
    {
        return $this->hasMany(ProjectPoNumber::class)->orderBy('id');
    }

    /**
     * @return HasMany<ProjectFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    /**
     * The files of one upload zone; the relation managers each use one of these.
     *
     * @return HasMany<ProjectFile, $this>
     */
    public function filesIn(ProjectFileSection $section): HasMany
    {
        return $this->files()->where('section', $section->value);
    }

    /**
     * @return HasMany<ProjectFile, $this>
     */
    public function documents(): HasMany
    {
        return $this->filesIn(ProjectFileSection::Documents);
    }

    /**
     * @return HasMany<ProjectFile, $this>
     */
    public function images(): HasMany
    {
        return $this->filesIn(ProjectFileSection::Images);
    }

    /**
     * @return HasMany<ProjectFile, $this>
     */
    public function offers(): HasMany
    {
        return $this->filesIn(ProjectFileSection::Offers);
    }

    /**
     * @return HasMany<ProjectFile, $this>
     */
    public function invoices(): HasMany
    {
        return $this->filesIn(ProjectFileSection::Invoices);
    }

    /**
     * @return HasMany<ProjectFile, $this>
     */
    public function plans(): HasMany
    {
        return $this->filesIn(ProjectFileSection::Plans);
    }

    /**
     * @return HasMany<ProjectFile, $this>
     */
    public function schematics(): HasMany
    {
        return $this->filesIn(ProjectFileSection::Schematics);
    }

    /**
     * @return HasMany<ProjectFile, $this>
     */
    public function extraFiles(): HasMany
    {
        return $this->filesIn(ProjectFileSection::Extra);
    }

    /**
     * @return HasMany<ProjectPart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(ProjectPart::class)->orderBy('id');
    }

    /**
     * Intervention categories (PREPARE, CONNECT, DECONNECT, …) of an intervention.
     *
     * @return BelongsToMany<InterventionCategory, $this>
     */
    public function interventionCategories(): BelongsToMany
    {
        return $this->belongsToMany(InterventionCategory::class)->orderBy('sort')->orderBy('name');
    }

    /**
     * @return BelongsTo<InterventionCategory, $this>
     */
    public function interventionSubcategory(): BelongsTo
    {
        return $this->belongsTo(InterventionCategory::class, 'intervention_subcategory_id');
    }

    /**
     * @return HasOne<InterventionReport, $this>
     */
    public function interventionReport(): HasOne
    {
        return $this->hasOne(InterventionReport::class);
    }

    /**
     * A project in an intervention category (600), whether or not the interventions module is on.
     */
    public function isIntervention(): bool
    {
        return (bool) $this->category?->numbering->isIntervention();
    }

    /**
     * "COLIBRI · PHASE 1 · FRM220UB, FRM220 FIRE".
     */
    public function interventionSubcategoryLabel(): ?string
    {
        if (! $this->interventionSubcategory) {
            return null;
        }

        return implode(' · ', array_filter([
            $this->interventionSubcategory->name,
            $this->intervention_phase,
            implode(', ', (array) $this->intervention_tags),
        ]));
    }

    /**
     * @return HasMany<PlanningItem, $this>
     */
    public function planningItems(): HasMany
    {
        return $this->hasMany(PlanningItem::class);
    }

    /**
     * Whether the user is in the planning for this project (while the planning module is on),
     * so they can open it from their planning.
     */
    public function isPlannedFor(User $user): bool
    {
        return modules()->planning() && $this->planningItems()->where('user_id', $user->id)->exists();
    }

    /**
     * Non-administrators only see projects they lead, that one of their teams is assigned to or
     * that they are in the planning for. Like everywhere else in the ERP this rule only applies
     * while the teams module is on.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesOnlyOwnTeams()) {
            $query->where(fn (Builder $query) => $query
                ->whereHas('leaders', fn (Builder $query) => $query->whereKey($user->id))
                ->orWhereHas('teams', fn (Builder $query) => $query->whereKey($user->teamIds()))
                ->when(modules()->planning(), fn (Builder $query) => $query->orWhereHas('planningItems', fn (Builder $query) => $query->where('user_id', $user->id))));
        }
    }

    public function isVisibleTo(User $user): bool
    {
        return ! $user->seesOnlyOwnTeams() || $this->isLedBy($user) || $this->teams()->whereKey($user->teamIds())->exists();
    }

    public function isLedBy(User $user): bool
    {
        return $this->leaders()->whereKey($user->id)->exists();
    }

    /**
     * 604-001-02GAM, 605-26001-02GAM or 606-001.
     */
    public function buildReference(): string
    {
        $category = $this->category;

        return implode('-', array_filter([
            $category->code,
            $category->numbering->formatNumber((int) $this->number),
            $category->numbering->usesWorkSite() ? $this->cow_code : null,
        ]));
    }

    /**
     * The client line for private projects, the short description for the others.
     */
    public function subtitle(): ?string
    {
        if ($this->isIntervention()) {
            return trim(implode(' · ', array_filter([filled($this->work_order_number) ? 'WO '.$this->work_order_number : null, $this->short_description]))) ?: null;
        }

        return $this->category?->numbering->hasClient()
            ? (trim(implode(' · ', array_filter([$this->client_name, $this->client_company]))) ?: null)
            : $this->short_description;
    }

    public function addressLine(): ?string
    {
        $streetLine = trim(implode(' ', array_filter([$this->street, $this->house_number, $this->addition])));
        $place = trim(implode(' ', array_filter([$this->postal_code, $this->city])));

        return implode(', ', array_filter([$streetLine, $place, $this->country?->localName()])) ?: null;
    }

    protected static function booted(): void
    {
        // "saving" runs before "creating", so the number is assigned here, before the reference is built.
        static::saving(function (self $project): void {
            if (! $project->exists && blank($project->number)) {
                // Lock the category while picking the number; the unique index (category, number) is the final guard.
                DB::transaction(function () use ($project): void {
                    $category = ProjectCategory::query()->lockForUpdate()->findOrFail($project->project_category_id);
                    $project->number = $category->nextNumber();
                });
            }

            $category = $project->category;

            if (! $category->numbering->usesWorkSite()) {
                $project->work_site_id = null;
            }

            if (! $category->has_short_description) {
                $project->short_description = null;
            }

            if ($project->isDirty('work_site_id') || blank($project->cow_code)) {
                $project->cow_code = $project->workSite?->cow_code ?? $project->cow_code;
            }

            $project->reference = $project->buildReference();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => ProjectStatus::class,
            'intervention_tags' => 'array',
        ];
    }
}
