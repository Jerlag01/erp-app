<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The bank of an account. ING, Belfius and KBC (with CBC) are read through Ponto; any bank can
 * still be followed with its CODA statements.
 */
enum Bank: string implements HasLabel
{
    case Ing = 'ing';

    case Belfius = 'belfius';

    case Kbc = 'kbc';

    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ing => 'ING',
            self::Belfius => 'Belfius',
            self::Kbc => 'KBC / CBC',
            self::Other => __('erp.banking.other_bank'),
        };
    }

    /**
     * The bank of a Belgian IBAN or BIC, as far as it can be told (else "other").
     */
    public static function guess(?string $iban, ?string $bic = null): self
    {
        $bic = strtoupper((string) $bic);

        foreach (['BBRU' => self::Ing, 'GKCC' => self::Belfius, 'KRED' => self::Kbc, 'CREG' => self::Kbc] as $prefix => $bank) {
            if (str_starts_with($bic, $prefix)) {
                return $bank;
            }
        }

        $iban = strtoupper(preg_replace('/\s+/', '', (string) $iban));

        if (! preg_match('/^BE\d{2}(\d{3})/', $iban, $match)) {
            return self::Other;
        }

        $code = (int) $match[1];

        return match (true) {
            $code >= 300 && $code <= 399 => self::Ing,
            $code >= 50 && $code <= 99, $code >= 550 && $code <= 569 => self::Belfius,
            $code >= 400 && $code <= 499, $code >= 730 && $code <= 749 => self::Kbc,
            default => self::Other,
        };
    }
}
