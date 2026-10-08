<?php

namespace App\Models;

use Database\Factories\WasteProcessorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A waste processor (collector / treatment company) that waste is sent to.
 */
#[Fillable(['name', 'street', 'house_number', 'postal_code', 'city', 'country_id', 'vat_number', 'permit_number', 'contact_name', 'phone', 'email', 'notes', 'is_active'])]
class WasteProcessor extends Model
{
    /** @use HasFactory<WasteProcessorFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<WasteEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(WasteEntry::class);
    }

    public function addressLine(): ?string
    {
        $streetLine = trim(implode(' ', array_filter([$this->street, $this->house_number])));
        $place = trim(implode(' ', array_filter([$this->postal_code, $this->city])));

        return implode(', ', array_filter([$streetLine, $place])) ?: null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
