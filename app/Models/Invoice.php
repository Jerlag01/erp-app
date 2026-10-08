<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\VatRate;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * An outgoing invoice or credit note. Drafts can be edited freely and have no number; finalising
 * gives the next number of the year (no gaps, as Belgian law requires) and locks the document.
 * Mistakes in a final invoice are corrected with a credit note.
 */
#[Fillable([
    'type', 'status', 'client_id', 'project_id', 'credited_invoice_id', 'allow_changes',
    'issue_date', 'due_date', 'subject', 'client_reference', 'notes', 'paid_on', 'created_by',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'invoice',
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
     * For a credit note: the invoice it credits.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function creditedInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credited_invoice_id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'credited_invoice_id');
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<ProjectPoNumber, $this>
     */
    public function poNumbers(): BelongsToMany
    {
        return $this->belongsToMany(ProjectPoNumber::class, 'invoice_po_numbers')->withTimestamps();
    }

    /**
     * @return HasMany<InvoiceReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(InvoiceReminder::class)->orderByDesc('sent_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCreditNote(): bool
    {
        return $this->type === InvoiceType::CreditNote;
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    /**
     * PO numbers shown on the document: its own, or for a credit note those of the credited invoice.
     *
     * @return list<string>
     */
    public function poNumberList(): array
    {
        $source = $this->isCreditNote() && $this->creditedInvoice ? $this->creditedInvoice : $this;

        return $source->poNumbers->pluck('number')->all();
    }

    /**
     * Recomputes subtotal, VAT and total from the lines. VAT is calculated per rate on the
     * rate's total (EN 16931), not per line.
     */
    public function recalculate(): void
    {
        $this->load('lines');
        $breakdown = $this->vatBreakdown();

        $this->forceFill([
            'subtotal' => round(array_sum(array_column($breakdown, 'base')), 2),
            'vat_total' => round(array_sum(array_column($breakdown, 'vat')), 2),
        ]);
        $this->total = round((float) $this->subtotal + (float) $this->vat_total, 2);
        $this->saveQuietly();
    }

    /**
     * @return array<string, array{rate: VatRate, base: float, vat: float}>
     */
    public function vatBreakdown(): array
    {
        $groups = [];

        foreach ($this->lines as $line) {
            $rate = $line->vat_rate ?? VatRate::TwentyOne;
            $groups[$rate->value] ??= ['rate' => $rate, 'base' => 0.0, 'vat' => 0.0];
            $groups[$rate->value]['base'] += (float) $line->line_total;
        }

        foreach ($groups as &$group) {
            $group['base'] = round($group['base'], 2);
            $group['vat'] = round($group['base'] * $group['rate']->percentage() / 100, 2);
        }

        return $groups;
    }

    public function hasReverseCharge(): bool
    {
        return $this->lines->contains(fn (InvoiceLine $line): bool => $line->vat_rate === VatRate::ReverseCharge);
    }

    /**
     * Gives the document its number and locks it.
     */
    public function finalize(): void
    {
        if (! $this->isDraft()) {
            throw new LogicException('Only drafts can be finalised.');
        }

        DB::transaction(function (): void {
            $year = (int) $this->issue_date->format('Y');
            $type = $this->type->value;

            DB::table('finance_counters')->insertOrIgnore(['type' => $type, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $counter = DB::table('finance_counters')->where(['type' => $type, 'year' => $year])->lockForUpdate()->first();
            $sequence = (int) $counter->last_number + 1;
            DB::table('finance_counters')->where('id', $counter->id)->update(['last_number' => $sequence, 'updated_at' => now()]);

            $this->recalculate();
            $this->forceFill([
                'number' => self::formatNumber($this->type, $year, $sequence),
                'number_year' => $year,
                'number_sequence' => $sequence,
                'structured_reference' => self::structuredReference($this->type, $year, $sequence),
                'status' => InvoiceStatus::Final,
                'finalized_at' => now(),
                'due_date' => $this->due_date ?? $this->issue_date->copy()->addDays($this->client->payment_term_days),
            ])->saveQuietly();
        });
    }

    public static function formatNumber(InvoiceType $type, int $year, int $sequence): string
    {
        $prefix = (string) settings()->get($type === InvoiceType::Invoice ? 'finance.invoice_prefix' : 'finance.credit_note_prefix', $type === InvoiceType::Invoice ? '' : 'CN');

        return $prefix.$year.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Belgian structured communication ("+++123/4567/89012+++"): 10 digits plus a mod-97 check.
     */
    public static function structuredReference(InvoiceType $type, int $year, int $sequence): string
    {
        $base = ($type === InvoiceType::Invoice ? '1' : '2').str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT).str_pad((string) ($sequence % 10_000_000), 7, '0', STR_PAD_LEFT);
        $check = (int) $base % 97 ?: 97;
        $digits = $base.str_pad((string) $check, 2, '0', STR_PAD_LEFT);

        return '+++'.substr($digits, 0, 3).'/'.substr($digits, 3, 4).'/'.substr($digits, 7, 5).'+++';
    }

    public function isOverdue(): bool
    {
        return ! $this->isCreditNote() && $this->status === InvoiceStatus::Sent && $this->due_date !== null && $this->due_date->lt(today());
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    /**
     * Sent invoices that are not paid yet.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->where('type', InvoiceType::Invoice)->where('status', InvoiceStatus::Sent);
    }

    /**
     * Invoices and credit notes that count for the figures (everything except drafts).
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeBooked(Builder $query): void
    {
        $query->where('status', '!=', InvoiceStatus::Draft);
    }

    public static function money(float|string|null $amount): string
    {
        return '€ '.number_format((float) $amount, 2, ',', '.');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'allow_changes' => 'boolean',
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_on' => 'date',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'finalized_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'reminder_level' => 'integer',
            'number_sequence' => 'integer',
        ];
    }
}
