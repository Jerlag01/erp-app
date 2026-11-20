<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CertificateTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A kind of certificate (VCA, BA4, forklift…), managed under "Opleidingen & medisch onderzoek" > certificate
 * types (like in Jacobs ERP). It starts with the former fixed types; types can be added, deactivated, or
 * deleted while no employee has one. Certificates point to it by its code, which never changes. Its
 * validity (months) sets the expiry date of its certificates from the date obtained; without one a
 * certificate never expires. Changing the validity recalculates every certificate of the type.
 */
#[Fillable(['code', 'name_nl', 'name_fr', 'name_en', 'short_nl', 'short_fr', 'short_en', 'overview', 'shows_validity', 'validity_months', 'kind', 'sort', 'is_active'])]
class CertificateType extends Model
{
    /** @use HasFactory<CertificateTypeFactory> */
    use HasFactory;

    /** A fixed column in the overview. */
    public const OVERVIEW_ALWAYS = 'always';

    /** A column only when somebody has this certificate. */
    public const OVERVIEW_HELD = 'held';

    /** Never in the overview. */
    public const OVERVIEW_NEVER = 'never';

    public const OVERVIEWS = [self::OVERVIEW_ALWAYS, self::OVERVIEW_HELD, self::OVERVIEW_NEVER];

    /** Asks for the licence categories. */
    public const KIND_DRIVING_LICENCE = 'driving_licence';

    /** Never expires and asks for the degree. */
    public const KIND_SCHOOL_DEGREE = 'school_degree';

    public const KINDS = [self::KIND_DRIVING_LICENCE, self::KIND_SCHOOL_DEGREE];

    /**
     * Belgian driving licence categories.
     */
    public const DRIVING_LICENCE_CATEGORIES = ['AM', 'A1', 'A2', 'A', 'B', 'BE', 'C1', 'C1E', 'C', 'CE', 'D1', 'D1E', 'D', 'DE', 'G'];

    /** @var array<string, self>|null every type by code, for this request */
    protected static ?array $byCode = null;

    /**
     * @return HasMany<EmployeeCertificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(EmployeeCertificate::class, 'type', 'code');
    }

    /**
     * @return HasMany<EmployeeCertificateRequest, $this>
     */
    public function certificateRequests(): HasMany
    {
        return $this->hasMany(EmployeeCertificateRequest::class, 'type', 'code');
    }

    public function isInUse(): bool
    {
        return $this->certificates()->exists() || $this->certificateRequests()->exists();
    }

    /**
     * The name in the current language (then Dutch, French, English when it is empty).
     */
    public function getLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) ($this->{"name_{$locale}"} ?? null ?: $this->name_nl ?: $this->name_fr ?: $this->name_en ?: $this->code);
    }

    /**
     * The column header in the overview: the short label, else the name.
     */
    public function shortLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) ($this->{"short_{$locale}"} ?? null ?: $this->short_nl ?: $this->getLabel($locale));
    }

    public function isDrivingLicence(): bool
    {
        return $this->kind === self::KIND_DRIVING_LICENCE;
    }

    public function isSchoolDegree(): bool
    {
        return $this->kind === self::KIND_SCHOOL_DEGREE;
    }

    /**
     * Whether it expires: only with a validity (a school degree never does).
     */
    public function canExpire(): bool
    {
        return $this->validity_months !== null && $this->validity_months > 0 && ! $this->isSchoolDegree();
    }

    /**
     * The expiry date of a certificate obtained on that date, or null when it never expires (or the date is unknown).
     */
    public function expiryFor(?CarbonInterface $obtainedOn): ?CarbonInterface
    {
        return $this->canExpire() && $obtainedOn !== null ? $obtainedOn->copy()->addMonthsNoOverflow($this->validity_months) : null;
    }

    /**
     * "5 jaar", "18 maanden", or "vervalt nooit".
     */
    public function validityLabel(): string
    {
        return match (true) {
            ! $this->canExpire() => __('erp.certificate_types.never_expires'),
            $this->validity_months % 12 === 0 => trans_choice('erp.certificate_types.years', intdiv($this->validity_months, 12), ['count' => intdiv($this->validity_months, 12)]),
            default => trans_choice('erp.certificate_types.months', $this->validity_months, ['count' => $this->validity_months]),
        };
    }

    /**
     * @param  Builder<CertificateType>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /**
     * The type with this code (also inactive ones), or null.
     */
    public static function forCode(?string $code): ?self
    {
        if (blank($code)) {
            return null;
        }

        static::$byCode ??= static::query()->ordered()->get()->keyBy('code')->all();

        return static::$byCode[$code] ?? null;
    }

    /**
     * Every type in the overview order, by code.
     *
     * @return array<string, self>
     */
    public static function allByCode(): array
    {
        static::forCode('-');

        return static::$byCode ?? [];
    }

    /**
     * Select options: code => name, in the overview order. Inactive types only when given (the current one).
     *
     * @return array<string, string>
     */
    public static function options(?string $including = null): array
    {
        return collect(static::allByCode())
            ->filter(fn (self $type): bool => $type->is_active || $type->code === $including)
            ->map(fn (self $type): string => $type->getLabel())
            ->all();
    }

    /**
     * A new code from the name: "Werken met asbest" → "werken_met_asbest" (with a number when taken).
     */
    public static function codeFor(string $name): string
    {
        $base = Str::limit(Str::slug($name, '_'), 50, '') ?: 'type';
        $code = $base;
        $number = 2;

        while (static::query()->where('code', $code)->exists()) {
            $code = $base.'_'.$number++;
        }

        return $code;
    }

    public static function flushCache(): void
    {
        static::$byCode = null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $type): void {
            $type->code = filled($type->code) ? $type->code : static::codeFor($type->name_nl ?: $type->name_fr ?: $type->name_en ?: 'type');
        });

        static::saved(function (self $type): void {
            static::flushCache();

            // A new validity: every certificate of the type gets its expiry date again.
            if (! $type->wasRecentlyCreated && $type->wasChanged(['validity_months', 'kind'])) {
                $type->certificates()->lazyById()->each(fn (EmployeeCertificate $certificate) => $certificate->save());
            }
        });
        static::deleted(fn () => static::flushCache());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shows_validity' => 'boolean',
            'validity_months' => 'integer',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
