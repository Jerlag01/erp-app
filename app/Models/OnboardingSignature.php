<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A user's reading of one onboarding document: when they opened it and their written signature
 * (name and timestamp) confirming they read it.
 */
#[Fillable(['user_id', 'onboarding_document_id', 'opened_at', 'signer_name', 'signature_path', 'signed_at'])]
class OnboardingSignature extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<OnboardingDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(OnboardingDocument::class, 'onboarding_document_id');
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    public function sign(string $name, string $dataUrl): void
    {
        $png = str_starts_with($dataUrl, 'data:image/png;base64,') ? base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true) : false;

        if ($png === false || ! str_starts_with($png, "\x89PNG")) {
            throw new \InvalidArgumentException('The signature is not a valid PNG image.');
        }

        $path = "onboarding/signatures/{$this->user_id}-{$this->onboarding_document_id}-".Str::random(12).'.png';
        Storage::disk(OnboardingDocument::DISK)->put($path, $png);

        $this->update(['signer_name' => mb_substr(trim($name), 0, 150), 'signature_path' => $path, 'signed_at' => now()]);
    }

    public function signatureDataUri(): ?string
    {
        $disk = Storage::disk(OnboardingDocument::DISK);

        return filled($this->signature_path) && $disk->exists($this->signature_path)
            ? 'data:image/png;base64,'.base64_encode((string) $disk->get($this->signature_path))
            : null;
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $signature) => filled($signature->signature_path) ? Storage::disk(OnboardingDocument::DISK)->delete($signature->signature_path) : null);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }
}
