<?php

namespace App\Models;

use App\Enums\Bank;
use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bank account of the company that is read (never paid from): through Ponto, or from the
 * CODA statements the bank provides.
 */
#[Fillable(['name', 'iban', 'bic', 'bank', 'provider', 'external_id', 'currency', 'balance', 'balance_at', 'last_synced_at', 'last_error', 'is_active'])]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    public const PONTO = 'ponto';

    public const CODA = 'coda';

    /**
     * @return HasMany<BankTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public static function normaliseIban(?string $iban): ?string
    {
        $iban = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $iban));

        return $iban === '' ? null : $iban;
    }

    public function formattedIban(): string
    {
        return trim(chunk_split((string) $this->iban, 4, ' '));
    }

    protected static function booted(): void
    {
        static::saving(function (self $account): void {
            $account->iban = self::normaliseIban($account->iban);
            $account->bic = filled($account->bic) ? strtoupper(trim((string) $account->bic)) : null;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bank' => Bank::class,
            'balance' => 'decimal:2',
            'balance_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
