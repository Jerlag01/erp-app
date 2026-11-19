<?php

namespace App\Models;

use App\Support\VatCodes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A VAT code invoice lines can have (21%, 6%, reverse charge, …), managed as finance master data. The
 * code is what lines, standard lines and clients store, so it cannot change once created. A reverse
 * charge (autoliquidatie / medecontractant) charges no VAT; the customer accounts for it. The legal
 * mention of every code on an invoice is printed under the totals, in the client's language.
 */
#[Fillable([
    'code', 'name', 'name_fr', 'name_en', 'percentage', 'category', 'is_reverse_charge', 'exemption_reason_code',
    'mention', 'mention_fr', 'mention_en', 'is_active', 'sort',
])]
class VatCode extends Model
{
    public const STANDARD = '21';

    public const REVERSE_CHARGE = 'reverse_charge';

    /**
     * UNCL5305 tax categories used by Peppol (EN 16931).
     */
    public const CATEGORIES = ['S', 'Z', 'E', 'AE', 'K', 'G', 'O'];

    /**
     * The exemption reason Peppol needs for the categories without VAT, when the code has none.
     *
     * @var array<string, string>
     */
    public const DEFAULT_EXEMPTION_REASONS = ['AE' => 'VATEX-EU-AE', 'K' => 'VATEX-EU-IC', 'G' => 'VATEX-EU-G', 'O' => 'VATEX-EU-O'];

    /**
     * The code by its stored value, also when it was removed later (then a plain stand-in).
     */
    public static function forCode(mixed $code): ?self
    {
        return app(VatCodes::class)->find($code);
    }

    /**
     * Active codes for a select, "21%" by code; the given (current) codes stay in the list when inactive.
     *
     * @return array<string, string>
     */
    public static function options(mixed ...$keep): array
    {
        return app(VatCodes::class)->options($keep);
    }

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) (match ($locale) {
            'fr' => $this->name_fr,
            'en' => $this->name_en,
            default => null,
        } ?: $this->name ?: $this->code);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * The legal mention on the invoice, in the given language, or null.
     */
    public function mention(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $text = match ($locale) {
            'fr' => $this->mention_fr,
            'en' => $this->mention_en,
            default => null,
        } ?: $this->mention;

        return filled($text) ? (string) $text : null;
    }

    public function percentage(): float
    {
        return $this->isReverseCharge() ? 0.0 : (float) $this->percentage;
    }

    public function isReverseCharge(): bool
    {
        return (bool) $this->is_reverse_charge;
    }

    /**
     * UNCL5305 tax category for Peppol: a reverse charge is always AE unless set to intra-EU (K).
     */
    public function ublCategory(): string
    {
        $category = strtoupper((string) $this->category) ?: 'S';

        if ($this->isReverseCharge() && ! in_array($category, ['AE', 'K'], true)) {
            return 'AE';
        }

        return $category;
    }

    /**
     * The VATEX code Peppol needs for a category without VAT, or null.
     */
    public function exemptionReasonCode(): ?string
    {
        return $this->exemption_reason_code ?: (self::DEFAULT_EXEMPTION_REASONS[$this->ublCategory()] ?? null);
    }

    /**
     * Whether invoice lines, standard lines or clients use this code (it can then not be removed).
     */
    public function isUsed(): bool
    {
        return DB::table('invoice_lines')->where('vat_rate', $this->code)->exists()
            || DB::table('standard_invoice_lines')->where('vat_rate', $this->code)->exists()
            || DB::table('clients')->where('default_vat_rate', $this->code)->exists();
    }

    protected static function booted(): void
    {
        static::saving(function (self $code): void {
            $code->category = strtoupper((string) $code->category) ?: 'S';

            if ($code->is_reverse_charge) {
                $code->percentage = 0;
            }
        });

        static::saved(fn () => app(VatCodes::class)->forget());
        static::deleted(fn () => app(VatCodes::class)->forget());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'is_reverse_charge' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
