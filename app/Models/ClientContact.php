<?php

namespace App\Models;

use Database\Factories\ClientContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A communication point of a client for invoicing: a person or department (accounting, purchasing,
 * a site) with its own e-mail address, receiving the invoices and/or the payment reminders. An
 * invoice can be addressed to one of them.
 */
#[Fillable(['client_id', 'name', 'role', 'email', 'phone', 'receives_invoices', 'receives_reminders', 'notes', 'sort'])]
class ClientContact extends Model
{
    /** @use HasFactory<ClientContactFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * "Boekhouding · Els Peeters — els@client.be".
     */
    public function label(): string
    {
        return trim(implode(' · ', array_filter([$this->role, $this->name])).' — '.$this->email);
    }

    protected static function booted(): void
    {
        static::saving(function (self $contact): void {
            $contact->email = strtolower(trim((string) $contact->email));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'receives_invoices' => 'boolean',
            'receives_reminders' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
