<?php

namespace App\Models;

use App\Enums\VatRate;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client (company or private person) that invoices go to. The internal memo is for
 * administrators only.
 */
#[Fillable([
    'short_name', 'company_name', 'is_private', 'emails', 'accounting_email', 'phone',
    'street', 'house_number', 'addition', 'postal_code', 'city', 'country_id', 'vat_number', 'peppol_id',
    'default_vat_rate', 'payment_term_days', 'locale', 'send_reminders', 'internal_memo', 'is_active',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Where invoices and reminders go: the accounting address, or else the first general address.
     */
    public function invoiceEmail(): ?string
    {
        return $this->accounting_email ?: (array_values(array_filter((array) $this->emails))[0] ?? null);
    }

    public function label(): string
    {
        return "{$this->short_name} · {$this->company_name}";
    }

    /**
     * @return list<string>
     */
    public function addressLines(): array
    {
        $street = trim(implode(' ', array_filter([$this->street, $this->house_number])).($this->addition ? ' '.$this->addition : ''));
        $place = trim(implode(' ', array_filter([$this->postal_code, $this->city])));

        return array_values(array_filter([$street, $place, $this->country && $this->country->code !== 'BE' ? $this->country->localName() : null]));
    }

    /**
     * "BE0123.456.789" → "BE0123456789".
     */
    public static function normaliseVat(?string $vat): ?string
    {
        $vat = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $vat));

        return $vat === '' ? null : $vat;
    }

    /**
     * Belgian VAT / enterprise numbers: BE + 10 digits with a mod-97 check. Other countries only
     * need a country code followed by 2–13 characters.
     */
    public static function isValidVat(string $vat): bool
    {
        $vat = (string) self::normaliseVat($vat);

        if (str_starts_with($vat, 'BE')) {
            $digits = substr($vat, 2);

            if (strlen($digits) === 9) {
                $digits = '0'.$digits;
            }

            return (bool) preg_match('/^[01]\d{9}$/', $digits) && 97 - ((int) substr($digits, 0, 8) % 97) === (int) substr($digits, 8, 2);
        }

        return (bool) preg_match('/^[A-Z]{2}[A-Z0-9]{2,13}$/', $vat);
    }

    /**
     * The Peppol participant id: as set, or derived from a Belgian enterprise number (scheme 0208).
     */
    public function peppolParticipant(): ?string
    {
        if (filled($this->peppol_id)) {
            return $this->peppol_id;
        }

        $vat = (string) $this->vat_number;

        return str_starts_with($vat, 'BE') ? '0208:'.substr(str_pad(substr($vat, 2), 10, '0', STR_PAD_LEFT), 0, 10) : null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $client): void {
            $client->vat_number = self::normaliseVat($client->vat_number);
            $client->short_name = strtoupper(trim((string) $client->short_name));
            $client->emails = array_values(array_unique(array_filter(array_map(fn ($email) => strtolower(trim((string) $email)), (array) $client->emails))));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'emails' => 'array',
            'default_vat_rate' => VatRate::class,
            'payment_term_days' => 'integer',
            'send_reminders' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
