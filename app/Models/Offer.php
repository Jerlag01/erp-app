<?php

namespace App\Models;

use App\Enums\InvoiceType;
use App\Enums\OfferStatus;
use App\Enums\VatRate;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * An offer (quote) for a project, laid out like the Proximus offer template: lines per category with
 * a supply (leveren) and an installation (plaatsen) price, the price of the works, the general costs
 * apart, and the number of workdays the installation price stands for (installation total ÷ hourly
 * rate, per crew size). Numbered on creation ("OF2026-0001"). A module of its own: without the finance
 * module the client is a name; with it, a finance client, and an accepted offer becomes a draft invoice.
 */
#[Fillable([
    'status', 'client_id', 'client_name', 'project_id', 'offer_date', 'valid_until', 'subject', 'client_reference', 'our_reference',
    'site_address', 'hourly_rate', 'hours_per_day', 'remarks', 'created_by',
])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The draft invoice made from this offer.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<OfferLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OfferLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * What a new offer starts with: the settings, and for a project its COW code, address and description.
     *
     * @return array<string, mixed>
     */
    public static function defaults(?Project $project = null): array
    {
        $site = $project?->workSite;
        $city = $site?->city ?? $project?->city;

        return [
            'status' => OfferStatus::Draft->value,
            'offer_date' => today()->toDateString(),
            'valid_until' => ($days = (int) settings()->get('offers.validity_days', 30)) > 0 ? today()->addDays($days)->toDateString() : null,
            'hourly_rate' => (float) settings()->get('offers.hourly_rate', 0),
            'hours_per_day' => (float) settings()->get('offers.hours_per_day', 8) ?: 8,
            'remarks' => settings()->get('offers.remarks'),
            'project_id' => $project?->id,
            'subject' => $project?->short_description,
            'client_reference' => $project ? (trim(($site?->cow_code ?? $project->reference).' '.mb_strtoupper((string) $city)) ?: null) : null,
            'site_address' => $project ? self::addressFor($project) : null,
        ];
    }

    /**
     * "Proximus 02NO1 / Mercuriusstraat 27 / 1930 ZAVENTEM".
     */
    public static function addressFor(Project $project): ?string
    {
        $site = $project->workSite;
        $owner = $site ? trim(($project->client_company ?: (string) settings()->get('offers.site_owner', 'Proximus')).' '.$site->cow_code) : ($project->client_company ?: $project->client_name);
        $street = $site
            ? trim(implode(' ', array_filter([$site->street, $site->house_number])).(filled($site->addition) ? ' '.$site->addition : ''))
            : trim(implode(' ', array_filter([$project->street, $project->house_number, $project->addition])));
        $place = $site
            ? trim(implode(' ', array_filter([$site->postal_code, mb_strtoupper((string) $site->city)])))
            : trim(implode(' ', array_filter([$project->postal_code, mb_strtoupper((string) $project->city)])));

        return implode("\n", array_filter([$owner, $street, $place])) ?: null;
    }

    /**
     * Recomputes the totals from the lines: supply and installation of the works, the general costs, the total.
     */
    public function recalculate(): void
    {
        $this->load('lines.category');
        $works = $this->lines->reject(fn (OfferLine $line): bool => (bool) $line->category?->is_general_costs);
        $general = $this->lines->filter(fn (OfferLine $line): bool => (bool) $line->category?->is_general_costs);

        $this->forceFill([
            'supply_total' => round((float) $works->sum('supply_total'), 2),
            'install_total' => round((float) $works->sum('install_total'), 2),
            'general_total' => round((float) $general->sum(fn (OfferLine $line): float => (float) $line->supply_total + (float) $line->install_total), 2),
        ]);
        $this->total = round((float) $this->supply_total + (float) $this->install_total + (float) $this->general_total, 2);
        $this->saveQuietly();
    }

    /**
     * The lines per category in the order of the categories (lines without a category last).
     *
     * @return Collection<int, array{category: ?OfferCategory, lines: Collection<int, OfferLine>, total: float}>
     */
    public function chapters(bool $generalCosts = false): Collection
    {
        return $this->lines
            ->filter(fn (OfferLine $line): bool => (bool) $line->category?->is_general_costs === $generalCosts)
            ->groupBy(fn (OfferLine $line): string => (string) $line->offer_category_id)
            ->map(fn (Collection $lines): array => [
                'category' => $lines->first()->category,
                'lines' => $lines->values(),
                'total' => round((float) $lines->sum(fn (OfferLine $line): float => (float) $line->supply_total + (float) $line->install_total), 2),
            ])
            ->sortBy(fn (array $chapter): array => [$chapter['category'] === null ? 1 : 0, $chapter['category']?->sort_order ?? 0, $chapter['category']?->code ?? ''])
            ->values();
    }

    public function worksTotal(): float
    {
        return round((float) $this->supply_total + (float) $this->install_total, 2);
    }

    /**
     * The working hours the installation price stands for.
     */
    public function labourHours(): float
    {
        return (float) $this->hourly_rate > 0 ? round((float) $this->install_total / (float) $this->hourly_rate, 2) : 0.0;
    }

    /**
     * Workdays with each crew size of the settings: [2 => 0.14, 3 => 0.09, 4 => 0.07].
     *
     * @return array<int, float>
     */
    public function workdays(): array
    {
        $hoursPerDay = (float) $this->hours_per_day ?: 8;

        return collect(self::crewSizes())
            ->mapWithKeys(fn (int $crew): array => [$crew => round((float) $this->install_total / max((float) $this->hourly_rate, 0.01) / ($hoursPerDay * $crew), 2)])
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function crewSizes(): array
    {
        return collect(explode(',', (string) settings()->get('offers.crew_sizes', '2,3,4')))
            ->map(fn (string $size): int => (int) trim($size))
            ->filter(fn (int $size): bool => $size > 0)
            ->unique()->values()->all();
    }

    public function isDraft(): bool
    {
        return $this->status === OfferStatus::Draft;
    }

    /**
     * The client on the offer: the finance client, else the name typed in (without the finance module).
     */
    public function clientLabel(): ?string
    {
        return $this->client?->company_name ?? $this->client_name;
    }

    /**
     * The company on the offer: the offer settings, else the finance company details, else the site name.
     *
     * @return array{name: string, vat: ?string}
     */
    public static function company(): array
    {
        return [
            'name' => (string) (settings()->get('offers.company_name') ?: settings()->get('finance.company_name') ?: settings()->siteName()),
            'vat' => settings()->get('offers.vat_number') ?: settings()->get('finance.vat_number'),
        ];
    }

    public function title(): string
    {
        return $this->number ?? __('erp.finance.offers.new');
    }

    /**
     * A draft invoice with one line per offer line (supply + installation), the general costs included.
     */
    public function createInvoice(?User $user = null): Invoice
    {
        return DB::transaction(function () use ($user): Invoice {
            $this->loadMissing(['lines.category', 'client']);
            $rate = $this->client?->default_vat_rate ?? VatRate::TwentyOne;

            $invoice = Invoice::query()->create([
                'type' => InvoiceType::Invoice,
                'client_id' => $this->client_id,
                'project_id' => $this->project_id,
                'issue_date' => today(),
                'subject' => $this->subject,
                'client_reference' => $this->client_reference,
                'notes' => __('erp.finance.offers.invoice_note', ['number' => $this->number]),
                'created_by' => $user?->id,
            ]);

            $sort = 0;

            foreach ($this->lines as $line) {
                if ($line->isText()) {
                    continue;
                }

                $invoice->lines()->create([
                    'description' => mb_substr(trim(($line->category?->code ? $line->category->code.' · ' : '').$line->description), 0, 255),
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'unit_price' => round((float) $line->supply_price + (float) $line->install_price, 2),
                    'vat_rate' => $rate,
                    'sort_order' => ++$sort,
                ]);
            }

            $invoice->recalculate();
            $this->forceFill(['invoice_id' => $invoice->id, 'status' => OfferStatus::Accepted])->saveQuietly();

            return $invoice;
        });
    }

    public static function formatNumber(int $year, int $sequence): string
    {
        return (string) settings()->get('offers.prefix', 'OF').$year.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        // Numbered on creation (offers need no gapless numbering, unlike invoices).
        static::creating(function (self $offer): void {
            if (filled($offer->number)) {
                return;
            }

            $year = (int) ($offer->offer_date?->format('Y') ?? now()->format('Y'));
            DB::table('finance_counters')->insertOrIgnore(['type' => 'offer', 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $counter = DB::table('finance_counters')->where(['type' => 'offer', 'year' => $year])->lockForUpdate()->first();
            $sequence = (int) $counter->last_number + 1;
            DB::table('finance_counters')->where('id', $counter->id)->update(['last_number' => $sequence, 'updated_at' => now()]);

            $offer->forceFill(['number' => self::formatNumber($year, $sequence), 'number_year' => $year, 'number_sequence' => $sequence]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'offer_date' => 'date',
            'valid_until' => 'date',
            'hourly_rate' => 'decimal:2',
            'hours_per_day' => 'decimal:2',
            'supply_total' => 'decimal:2',
            'install_total' => 'decimal:2',
            'general_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
