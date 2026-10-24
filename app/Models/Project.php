<?php

namespace App\Models;

use App\Actions\Batteries\CreateBaeOrder;
use App\Actions\Projects\CloseProject;
use App\Actions\Projects\CreateFollowUpProject;
use App\Enums\ProjectFileSection;
use App\Enums\ProjectStatus;
use App\Enums\SiteLocationType;
use App\Support\Asbuilts;
use App\Support\ProjectProgress;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A project in a category, e.g. 604-0001-02GAM. Only administrators, its project leaders and the
 * teams assigned to it can see it (while the teams module is on).
 */
#[Fillable([
    'project_category_id', 'number', 'work_order_number', 'work_site_id', 'location_type', 'short_description', 'status',
    'intervention_subcategory_id', 'intervention_phase', 'intervention_tags',
    'client_name', 'client_company', 'client_email', 'client_phone',
    'street', 'house_number', 'addition', 'postal_code', 'city', 'country_id', 'notes', 'created_by', 'source_project_id',
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
    /**
     * Ticked steps of the progress bar (610–650 projects).
     *
     * @return HasMany<ProjectStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class);
    }

    /**
     * The project this one was copied from, or the 604 project a 605 follows up.
     *
     * @return BelongsTo<Project, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_project_id');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

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
     * Charging stations of a 608 project.
     *
     * @return HasMany<ChargingStation, $this>
     */
    public function chargingStations(): HasMany
    {
        return $this->hasMany(ChargingStation::class);
    }

    /**
     * The latest battery dossier (BAE, 604) of the project.
     *
     * @return HasOne<BatteryDossier, $this>
     */
    public function batteryDossier(): HasOne
    {
        return $this->hasOne(BatteryDossier::class)->latestOfMany();
    }

    /**
     * @return HasOne<BaeOrder, $this>
     */
    public function baeOrder(): HasOne
    {
        return $this->hasOne(BaeOrder::class);
    }

    /**
     * Followed-up deliveries: Enersys batteries (605) or electrical boards (610–650).
     *
     * @return HasMany<DeliveryFollowUp, $this>
     */
    public function deliveryFollowUps(): HasMany
    {
        return $this->hasMany(DeliveryFollowUp::class);
    }

    /**
     * @return HasMany<BatteryDelivery, $this>
     */
    public function batteryDeliveries(): HasMany
    {
        return $this->hasMany(BatteryDelivery::class)->orderBy('planned_delivery_date')->orderBy('id');
    }

    /**
     * @return HasOne<Asbuilt, $this>
     */
    public function asbuilt(): HasOne
    {
        return $this->hasOne(Asbuilt::class);
    }

    /**
     * GPW requests, newest first.
     *
     * @return HasMany<GpwRequest, $this>
     */
    public function gpwRequests(): HasMany
    {
        return $this->hasMany(GpwRequest::class)->latest('id');
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
     * 604-0001-02GAM, 605-260001-02GAM or 606-0001.
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
     * Whether projects of this category can be at a third-party client: the charging stations category (608).
     */
    public static function allowsThirdParty(?ProjectCategory $category): bool
    {
        return $category !== null && $category->code === settings()->get('charging.project_category', '608');
    }

    /**
     * At a third-party client with its own address, instead of a COW site.
     */
    public function isThirdParty(): bool
    {
        return $this->location_type === SiteLocationType::Other;
    }

    /**
     * The client line for private projects, the short description for the others.
     */
    public function subtitle(): ?string
    {
        if ($this->isThirdParty()) {
            return trim(implode(' · ', array_filter([$this->client_company ?: $this->client_name, $this->short_description]))) ?: null;
        }

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

            // Only charging (608) projects can be at a third-party client instead of a COW site.
            if (! self::allowsThirdParty($category)) {
                $project->location_type = SiteLocationType::Cow;
            } elseif ($project->isThirdParty()) {
                $project->work_site_id = null;
                $project->cow_code = null;
            }

            if (! $category->has_short_description) {
                $project->short_description = null;
            }

            if (! $project->isThirdParty() && ($project->isDirty('work_site_id') || blank($project->cow_code))) {
                $project->cow_code = $project->workSite?->cow_code ?? $project->cow_code;
            }

            $project->reference = $project->buildReference();

            // The moment it went to "executed": kept while it moves on (invoicing, closed), cleared when it goes back.
            if ($project->isDirty('status')) {
                if (in_array($project->status, ProjectStatus::executedOrLater(), true)) {
                    $project->executed_at ??= now();
                } elseif ($project->status !== ProjectStatus::Closed) {
                    $project->executed_at = null;
                }
            }
        });

        static::created(function (self $project): void {
            ProjectProgress::sync($project);
            app(CreateFollowUpProject::class)->handle($project);

            // Executed: the as-built of the project starts (not for the excluded categories).
            if ($project->status === ProjectStatus::Executed) {
                app(Asbuilts::class)->createFor($project);
            }

            // A 604 project starts with its BAE order (Excel calc.xlsx).
            $by = Auth::guard('web')->user();
            app(CreateBaeOrder::class)->handle($project, $by instanceof User ? $by : null);
        });

        // Status changes: closing (and "invoiced" on 610–650 projects) archives the project; an executed
        // 604 in Flanders or Brussels gets its 605.
        static::updated(function (self $project): void {
            if (! $project->wasChanged('status')) {
                return;
            }

            if ($project->status === ProjectStatus::Closed || ($project->status === ProjectStatus::Invoiced && ProjectProgress::appliesTo($project))) {
                app(CloseProject::class)->handle($project);

                return;
            }

            ProjectProgress::sync($project);
            app(CreateFollowUpProject::class)->handle($project);

            if ($project->status === ProjectStatus::Executed) {
                app(Asbuilts::class)->createFor($project);
            }
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
            'location_type' => SiteLocationType::class,
            'intervention_tags' => 'array',
            'archived_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }
}
