<?php

namespace App\Models;

use App\Enums\InterventionReportStatus;
use App\Enums\MaterialDirection;
use Database\Factories\InterventionReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * What the employee filled in when closing an intervention, reviewed by an administrator. One
 * per intervention: closing again after a rejection replaces it. The execution date is set by
 * the system when closing; the PDF is made for approval and invoicing.
 */
#[Fillable([
    'project_id', 'status', 'work_site_id', 'cow_code', 'room', 'hours_worked', 'remarks', 'categories', 'modified_boards',
    'executed_at', 'closed_by', 'reviewed_by', 'reviewed_at', 'review_note', 'po_number', 'pdf_path',
])]
class InterventionReport extends Model
{
    /** @use HasFactory<InterventionReportFactory> */
    use HasFactory;

    public const DISK = 'local';

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<WorkSite, $this>
     */
    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The colleagues who carried out the intervention with the employee who closed it.
     *
     * @return BelongsToMany<User, $this>
     */
    public function coWorkers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'intervention_report_user');
    }

    /**
     * @return HasMany<InterventionReportMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(InterventionReportMaterial::class)->orderBy('id');
    }

    /**
     * @return HasMany<InterventionReportPosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(InterventionReportPosition::class)->orderBy('id');
    }

    public function isPending(): bool
    {
        return $this->status === InterventionReportStatus::Pending;
    }

    /**
     * @return list<string>
     */
    public function categoryNames(): array
    {
        return array_values((array) $this->categories);
    }

    /**
     * @return list<string>
     */
    public function boardNames(): array
    {
        return array_values(array_map(fn (mixed $board): string => is_array($board) ? (string) ($board['name'] ?? '') : (string) $board, (array) $this->modified_boards));
    }

    /**
     * The employee who closed it followed by the co-workers.
     *
     * @return list<string>
     */
    public function workerNames(): array
    {
        return array_values(array_filter([$this->closer?->name, ...$this->coWorkers->pluck('name')->all()]));
    }

    /**
     * @return list<InterventionReportMaterial>
     */
    public function materialsIn(MaterialDirection $direction): array
    {
        return $this->materials->filter(fn (InterventionReportMaterial $material): bool => $material->direction === $direction)->values()->all();
    }

    public function hoursLabel(): string
    {
        $minutes = (int) round((float) $this->hours_worked * 60);

        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    protected static function booted(): void
    {
        static::deleted(function (self $report): void {
            if (filled($report->pdf_path)) {
                Storage::disk(self::DISK)->delete($report->pdf_path);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InterventionReportStatus::class,
            'hours_worked' => 'decimal:2',
            'categories' => 'array',
            'modified_boards' => 'array',
            'executed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
