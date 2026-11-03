<?php

namespace App\Models;

use Database\Factories\InterimOfficeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An interim office (Randstad, Adecco, …) with its contacts. The contacts get the weekly prestation
 * sheets of the interim employees linked to the office.
 */
#[Fillable(['name', 'is_active', 'notes'])]
class InterimOffice extends Model
{
    /** @use HasFactory<InterimOfficeFactory> */
    use HasFactory;

    /**
     * @return HasMany<InterimOfficeContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(InterimOfficeContact::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * The valid e-mail addresses of the contacts, as address => name.
     *
     * @return array<string, string>
     */
    public function contactAddresses(): array
    {
        return $this->contacts
            ->filter(fn (InterimOfficeContact $contact): bool => filter_var($contact->email, FILTER_VALIDATE_EMAIL) !== false)
            ->mapWithKeys(fn (InterimOfficeContact $contact): array => [mb_strtolower($contact->email) => $contact->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
