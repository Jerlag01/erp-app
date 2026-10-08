<?php

namespace App\Models;

use App\Enums\ItStatusType;
use Database\Factories\ItStatusLabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An asset status such as "Ready to deploy" or "Broken". Only deployable statuses allow a checkout.
 */
#[Fillable(['name', 'type', 'color'])]
class ItStatusLabel extends Model
{
    /** @use HasFactory<ItStatusLabelFactory> */
    use HasFactory;

    /**
     * @return HasMany<ItAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(ItAsset::class);
    }

    /**
     * Whether assets or other IT records still use this, so it cannot be deleted.
     */
    public function isInUse(): bool
    {
        return $this->assets()->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ItStatusType::class,
        ];
    }
}
