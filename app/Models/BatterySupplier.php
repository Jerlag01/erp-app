<?php

namespace App\Models;

use Database\Factories\BatterySupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A battery supplier (e.g. BAE) the BAE orders are mailed to, in its language. One supplier can be the
 * default: new orders start with it.
 */
#[Fillable(['name', 'email', 'cc_emails', 'contact_name', 'phone', 'address', 'locale', 'notes', 'is_default', 'is_active'])]
class BatterySupplier extends Model
{
    /** @use HasFactory<BatterySupplierFactory> */
    use HasFactory;

    /**
     * Languages of the order mail (those of the mail templates).
     */
    public const LOCALES = ['en' => 'English', 'nl' => 'Nederlands', 'fr' => 'Français'];

    /**
     * @return HasMany<BaeOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(BaeOrder::class);
    }

    public static function defaultSupplier(): ?self
    {
        return self::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->first();
    }

    /**
     * @return list<string>
     */
    public function ccEmails(): array
    {
        return array_values(array_filter(array_map('trim', (array) $this->cc_emails), fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
    }

    public function mailLocale(): string
    {
        return array_key_exists((string) $this->locale, self::LOCALES) ? (string) $this->locale : 'en';
    }

    protected static function booted(): void
    {
        // Only one default.
        static::saved(function (self $supplier): void {
            if ($supplier->is_default && ($supplier->wasChanged('is_default') || $supplier->wasRecentlyCreated)) {
                self::query()->whereKeyNot($supplier->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cc_emails' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
