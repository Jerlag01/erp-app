<?php

namespace App\Models;

use App\Enums\BankTransactionStatus;
use Database\Factories\BankTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A movement on a bank account. Money in can pay an invoice, money out an incoming invoice;
 * the link is made automatically when the structured communication and the amount match, or by
 * hand. Nothing is ever paid from here.
 */
#[Fillable([
    'bank_account_id', 'external_id', 'source', 'booking_date', 'value_date', 'amount', 'currency',
    'counterparty_name', 'counterparty_iban', 'remittance', 'structured_reference', 'status',
    'matchable_type', 'matchable_id', 'matched_by', 'matched_at',
])]
class BankTransaction extends Model
{
    /** @use HasFactory<BankTransactionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'unmatched',
        'currency' => 'EUR',
    ];

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * The invoice (money in) or incoming invoice (money out) this movement paid.
     *
     * @return MorphTo<Model, $this>
     */
    public function matchable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function isIncoming(): bool
    {
        return (float) $this->amount > 0;
    }

    /**
     * The 12 digits of a Belgian structured communication found in a text ("+++123/4567/89012+++",
     * "***…***" or the bare digits), only when its mod-97 check is right.
     */
    public static function structuredReferenceFrom(?string $text): ?string
    {
        $text = (string) $text;
        $candidates = [];

        if (preg_match_all('/[+*]{3}\s*(\d{3})\s*\/?\s*(\d{4})\s*\/?\s*(\d{5})\s*[+*]{3}/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $candidates[] = $match[1].$match[2].$match[3];
            }
        }

        if (preg_match_all('/(?<!\d)(\d{3})\/(\d{4})\/(\d{5})(?!\d)|(?<!\d)(\d{12})(?!\d)/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $candidates[] = ($match[4] ?? '') !== '' ? $match[4] : $match[1].$match[2].$match[3];
            }
        }

        foreach ($candidates as $digits) {
            if (self::isValidStructured($digits)) {
                return $digits;
            }
        }

        return null;
    }

    public static function isValidStructured(string $digits): bool
    {
        if (! preg_match('/^\d{12}$/', $digits)) {
            return false;
        }

        $check = (int) substr($digits, 0, 10) % 97 ?: 97;

        return $check === (int) substr($digits, 10, 2);
    }

    /**
     * "+++123/4567/89012+++" for 12 digits.
     */
    public static function formatStructured(?string $digits): ?string
    {
        return $digits && strlen($digits) === 12
            ? '+++'.substr($digits, 0, 3).'/'.substr($digits, 3, 4).'/'.substr($digits, 7, 5).'+++'
            : null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $transaction): void {
            $transaction->counterparty_iban = ($iban = BankAccount::normaliseIban($transaction->counterparty_iban)) ? substr($iban, 0, 34) : null;
            $transaction->structured_reference ??= self::structuredReferenceFrom($transaction->remittance);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BankTransactionStatus::class,
            'booking_date' => 'date',
            'value_date' => 'date',
            'amount' => 'decimal:2',
            'matched_at' => 'datetime',
        ];
    }
}
