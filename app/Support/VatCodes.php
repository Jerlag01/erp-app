<?php

namespace App\Support;

use App\Models\VatCode;
use Illuminate\Database\Eloquent\Collection;

/**
 * The VAT codes, loaded once per request (lines of invoices look theirs up for every total).
 */
class VatCodes
{
    /** @var Collection<string, VatCode>|null */
    protected ?Collection $codes = null;

    /**
     * @return Collection<string, VatCode>
     */
    public function all(): Collection
    {
        return $this->codes ??= VatCode::query()->orderBy('sort')->orderBy('code')->get()->keyBy('code');
    }

    public function find(mixed $code): ?VatCode
    {
        if ($code instanceof VatCode) {
            return $code;
        }

        if ($code === null || $code === '') {
            return null;
        }

        $code = (string) $code;

        // A code that no longer exists: a stand-in so old documents keep their totals.
        return $this->all()->get($code) ?? new VatCode([
            'code' => $code,
            'name' => is_numeric($code) ? $code.'%' : $code,
            'percentage' => is_numeric($code) ? (float) $code : 0,
            'category' => is_numeric($code) && (float) $code > 0 ? 'S' : 'Z',
        ]);
    }

    /**
     * @param  array<int, mixed>  $keep
     * @return array<string, string>
     */
    public function options(array $keep = []): array
    {
        $keep = array_map(fn (mixed $code): string => $code instanceof VatCode ? $code->code : (string) $code, array_filter($keep, fn (mixed $code): bool => filled($code)));

        return $this->all()
            ->filter(fn (VatCode $code): bool => $code->is_active || in_array($code->code, $keep, true))
            ->mapWithKeys(fn (VatCode $code): array => [$code->code => $code->label()])
            ->all();
    }

    public function forget(): void
    {
        $this->codes = null;
    }
}
