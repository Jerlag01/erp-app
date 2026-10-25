<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * An onboarding document in one language: new users in that language open it and sign that they read it.
 */
#[Fillable(['title', 'path', 'original_name', 'locale', 'sort', 'is_active'])]
class OnboardingDocument extends Model
{
    public const DISK = 'local';

    public const LOCALES = ['nl' => 'Nederlands', 'fr' => 'Français', 'en' => 'English'];

    /**
     * @return HasMany<OnboardingSignature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(OnboardingSignature::class);
    }

    /**
     * Active documents in a language, in their order.
     *
     * @param  Builder<OnboardingDocument>  $query
     */
    public function scopeFor(Builder $query, string $locale): void
    {
        $query->where('is_active', true)->where('locale', $locale)->orderBy('sort')->orderBy('id');
    }

    protected static function booted(): void
    {
        static::updated(function (self $document): void {
            if ($document->wasChanged('path') && filled($old = $document->getOriginal('path'))) {
                Storage::disk(self::DISK)->delete($old);
            }
        });

        static::deleted(fn (self $document) => Storage::disk(self::DISK)->delete($document->path));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
