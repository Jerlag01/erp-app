<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The delivery report ("ontvangstbewijs") of an IT asset handed to an employee: what was handed over (a
 * snapshot, so later changes to the asset do not change it), the device taken back, the conditions of
 * use and both signatures. The employee signs it on the platform or in the app; the signed PDF then goes
 * to their signed documents. Printed in the employee's language.
 */
#[Fillable([
    'it_asset_id', 'user_id', 'issued_by', 'handed_over_on', 'details',
    'company_signer_name', 'company_signature_path', 'signer_name', 'signature_path', 'signed_at',
])]
class ItDeliveryReport extends Model
{
    public const DISK = 'local';

    /** The fields of the report, kept in "details". */
    public const DETAILS = [
        'job_title', 'site', 'device', 'serial', 'imei', 'phone_number', 'sim', 'accessories', 'condition',
        'old_device', 'old_imei', 'old_condition', 'old_wiped',
    ];

    /** The state of the handed-over device. */
    public const CONDITIONS = ['new', 'refurbished'];

    /**
     * @return BelongsTo<ItAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return MorphOne<SignedDocument, $this>
     */
    public function signedDocument(): MorphOne
    {
        return $this->morphOne(SignedDocument::class, 'source');
    }

    public function scopeUnsigned(Builder $query): void
    {
        $query->whereNull('signed_at');
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    public function detail(string $key): mixed
    {
        return ((array) $this->details)[$key] ?? null;
    }

    /**
     * The language of the report: the employee's language setting.
     */
    public function locale(): string
    {
        $locale = (string) ($this->user?->preferredLocale() ?? config('app.locale'));

        return in_array($locale, ['nl', 'fr', 'en'], true) ? $locale : 'nl';
    }

    /**
     * "Dell Latitude 5440 (IT-0012)": the device as handed over.
     */
    public function deviceLabel(): string
    {
        $device = trim((string) $this->detail('device'));
        $tag = $this->asset?->asset_tag;

        return trim($device.($tag ? " ({$tag})" : '')) ?: (string) $tag;
    }

    /**
     * The employee, an administrator or someone who hands out IT assets.
     */
    public function mayBeViewedBy(?User $user): bool
    {
        return $user !== null && ($user->id === $this->user_id || $user->isAdmin() || $user->hasPermission('it_assets.checkout'));
    }

    public function sign(string $name, string $dataUrl): void
    {
        $this->update([
            'signer_name' => mb_substr(trim($name), 0, 150),
            'signature_path' => self::storeSignature($dataUrl, "it-delivery/{$this->id}/employee"),
            'signed_at' => now(),
        ]);
    }

    /**
     * Stores a drawn signature (PNG data URL); returns its path.
     */
    public static function storeSignature(string $dataUrl, string $prefix): string
    {
        $png = str_starts_with($dataUrl, 'data:image/png;base64,') ? base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true) : false;

        if ($png === false || ! str_starts_with($png, "\x89PNG")) {
            throw new \InvalidArgumentException('The signature is not a valid PNG image.');
        }

        $path = $prefix.'-'.Str::random(12).'.png';
        Storage::disk(self::DISK)->put($path, $png);

        return $path;
    }

    public function signatureDataUri(?string $path): ?string
    {
        $disk = Storage::disk(self::DISK);

        return filled($path) && $disk->exists($path) ? 'data:image/png;base64,'.base64_encode((string) $disk->get($path)) : null;
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $report) => Storage::disk(self::DISK)->delete(array_values(array_filter([$report->signature_path, $report->company_signature_path]))));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'handed_over_on' => 'date',
            'signed_at' => 'datetime',
        ];
    }
}
