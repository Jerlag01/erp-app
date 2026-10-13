<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A written signature on one document of a battery dossier (delivery, measurement or inspection).
 */
#[Fillable(['battery_dossier_id', 'document', 'signer_name', 'path', 'signed_by', 'signed_at'])]
class BatteryDossierSignature extends Model
{
    /**
     * @return BelongsTo<BatteryDossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(BatteryDossier::class, 'battery_dossier_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    /**
     * The signature as a data URI, for the form and the PDF.
     */
    public function dataUri(): ?string
    {
        $disk = Storage::disk(BatteryDossier::DISK);

        return $disk->exists($this->path) ? 'data:image/png;base64,'.base64_encode((string) $disk->get($this->path)) : null;
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $signature) => Storage::disk(BatteryDossier::DISK)->delete($signature->path));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }
}
