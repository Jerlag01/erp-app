<?php

namespace App\Models;

use App\Actions\Batteries\RegisterSentBaeOrder;
use App\Enums\ProjectStatus;
use Carbon\CarbonInterface;
use Database\Factories\BatteryDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One line of the "Overzicht Levering Batterijen": a battery order for a 604 project. The location
 * and order number come from the project. Ticking "delivered" stamps the system date and time and
 * moves the project to the status set in Settings > Batteries (to be executed, i.e. ready to plan).
 * Three internal documents (DOCUMENTS) belong to it: never shown in the client portal, and only with
 * "batteries.documents" (and "batteries.documents_upload" to change them).
 */
#[Fillable([
    'project_id', 'order_confirmation', 'battery_model_id', 'pieces', 'planned_delivery_date', 'delivery_date_confirmed', 'executed', 'total_cost', 'notes',
    'order_document', 'order_document_name', 'confirmation_document', 'confirmation_document_name', 'delivery_note_document', 'delivery_note_document_name',
])]
class BatteryDelivery extends Model
{
    /** @use HasFactory<BatteryDeliveryFactory> */
    use HasFactory;

    public const DISK = 'local';

    /** The documents: the order to the supplier, the supplier's confirmation, the delivery note. */
    public const DOCUMENTS = ['order', 'confirmation', 'delivery_note'];

    public function documentPath(string $kind): ?string
    {
        return in_array($kind, self::DOCUMENTS, true) ? $this->getAttribute($kind.'_document') : null;
    }

    public function documentName(string $kind): ?string
    {
        return $this->getAttribute($kind.'_document_name') ?: (($path = $this->documentPath($kind)) ? basename($path) : null);
    }

    public function hasDocument(string $kind): bool
    {
        return filled($this->documentPath($kind));
    }

    protected static function booted(): void
    {
        // Replaced or removed documents go from the disk too.
        static::updated(function (self $delivery): void {
            foreach (self::DOCUMENTS as $kind) {
                if ($delivery->wasChanged($kind.'_document') && filled($old = $delivery->getOriginal($kind.'_document'))) {
                    Storage::disk(self::DISK)->delete($old);
                }
            }
        });

        static::deleted(function (self $delivery): void {
            Storage::disk(self::DISK)->delete(array_values(array_filter(array_map(fn (string $kind): ?string => $delivery->documentPath($kind), self::DOCUMENTS))));
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<BatteryModel, $this>
     */
    public function batteryModel(): BelongsTo
    {
        return $this->belongsTo(BatteryModel::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * @param  Builder<BatteryDelivery>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('project', fn (Builder $query) => $query->visibleTo($user, 'batteries'));
    }

    /**
     * "55MUN - Munkzwalm": the COW code of the project's work site and its city.
     */
    public function location(): string
    {
        $site = $this->project?->workSite;
        $cow = $site?->cow_code ?? $this->project?->cow_code;

        return trim(implode(' - ', array_filter([$cow, $site?->city])));
    }

    /**
     * The order number: the project number within its category, e.g. "764" for 604-764-55MUN.
     */
    public function orderNumber(): string
    {
        $project = $this->project;

        return $project?->category ? $project->category->numbering->formatNumber((int) $project->number) : (string) $project?->number;
    }

    /**
     * When the batteries are planned to be installed: the execution date of the battery dossier, or else
     * the first day the project is in the planning.
     */
    public function plannedInstallationDate(): ?CarbonInterface
    {
        $project = $this->project;

        if (! $project) {
            return null;
        }

        if ($date = $project->batteryDossier?->execution_date) {
            return $date;
        }

        // Loaded with withMin('planningItems', 'date') in lists, else asked here.
        $first = array_key_exists('planning_items_min_date', $project->getAttributes())
            ? $project->getAttribute('planning_items_min_date')
            : $project->planningItems()->min('date');

        return $first ? Carbon::parse($first) : null;
    }

    /**
     * The order confirmation number is still the placeholder of a sent order ("0000000").
     */
    public function confirmationToBeConfirmed(): bool
    {
        return $this->order_confirmation === RegisterSentBaeOrder::UNCONFIRMED_NUMBER;
    }

    /**
     * The delivery date is the requested one of the order, not yet confirmed by the supplier.
     */
    public function deliveryDateToBeConfirmed(): bool
    {
        return $this->planned_delivery_date !== null && ! $this->delivery_date_confirmed;
    }

    public function markDelivered(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->forceFill(['delivered' => true, 'delivered_at' => now(), 'delivered_by' => $user->id])->save();

            if ($status = self::deliveredProjectStatus()) {
                $this->project->update(['status' => $status]);
            }
        });
    }

    /**
     * Unticking clears the delivery date; the project keeps its status.
     */
    public function unmarkDelivered(): void
    {
        $this->forceFill(['delivered' => false, 'delivered_at' => null, 'delivered_by' => null])->save();
    }

    public static function deliveredProjectStatus(): ?ProjectStatus
    {
        return ProjectStatus::tryFrom((string) settings()->get('batteries.delivered_project_status'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pieces' => 'integer',
            'planned_delivery_date' => 'date',
            'delivery_date_confirmed' => 'boolean',
            'delivered' => 'boolean',
            'delivered_at' => 'datetime',
            'executed' => 'boolean',
            'total_cost' => 'decimal:2',
        ];
    }
}
