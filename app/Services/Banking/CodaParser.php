<?php

namespace App\Services\Banking;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Reads a CODA file (the Belgian bank statement format, version 2, 128 characters a line) that ING,
 * Belfius, KBC and every other Belgian bank provide. Only the movements themselves are kept
 * (detail 0000); the detail lines of a grouped movement only break down its total.
 */
class CodaParser
{
    /**
     * @return list<array{iban: string, currency: string, balance: ?float, balance_date: ?string, transactions: list<array{external_id: string, booking_date: string, value_date: ?string, amount: float, counterparty_name: ?string, counterparty_iban: ?string, remittance: ?string, structured_reference: ?string}>}>
     */
    public function parse(string $content): array
    {
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $statements = [];
        $statement = null;
        $movement = null;
        $sawHeader = false;

        $finishMovement = function () use (&$movement, &$statement): void {
            if ($movement !== null && $statement !== null) {
                $statement['transactions'][] = $this->movement($movement, $statement['iban']);
            }

            $movement = null;
        };

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $line = str_pad($line, 128);
            $field = fn (int $from, int $to): string => mb_substr($line, $from - 1, $to - $from + 1);

            switch ($line[0]) {
                case '0':
                    $sawHeader = true;
                    break;
                case '1':
                    $finishMovement();
                    [$iban, $currency] = $this->account($line[1], $field);
                    $statement = ['iban' => $iban, 'currency' => $currency, 'balance' => null, 'balance_date' => null, 'transactions' => []];
                    break;
                case '2':
                    if ($statement === null) {
                        break;
                    }

                    if ($line[1] === '1') {
                        $finishMovement();

                        // Only the movement itself, not the details of a grouped one.
                        if ($field(7, 10) !== '0000') {
                            break;
                        }

                        $movement = [
                            'sequence' => $field(3, 6),
                            'bank_reference' => trim($field(11, 31)),
                            'amount' => ($field(32, 32) === '1' ? -1 : 1) * ((int) $field(33, 47)) / 1000,
                            'value_date' => $this->date($field(48, 53)),
                            'structured' => $field(62, 62) === '1',
                            'communication' => $field(63, 115),
                            'entry_date' => $this->date($field(116, 121)),
                            'statement' => $field(122, 124),
                            'counterparty_iban' => null,
                            'counterparty_name' => null,
                        ];
                    } elseif ($movement !== null && $line[1] === '2') {
                        $movement['communication'] .= $field(11, 63);
                    } elseif ($movement !== null && $line[1] === '3') {
                        $movement['counterparty_iban'] = $this->counterpartyIban($field(11, 47));
                        $movement['counterparty_name'] = trim($field(48, 82)) ?: null;
                        $movement['communication'] .= $field(83, 125);
                    }
                    break;
                case '8':
                    $finishMovement();

                    if ($statement !== null) {
                        $statement['balance'] = ($field(42, 42) === '1' ? -1 : 1) * ((int) $field(43, 57)) / 1000;
                        $statement['balance_date'] = $this->date($field(58, 63));
                        $statements[] = $statement;
                        $statement = null;
                    }
                    break;
                case '9':
                    $finishMovement();
                    break;
            }
        }

        $finishMovement();

        if ($statement !== null) {
            $statements[] = $statement;
        }

        if (! $sawHeader || $statements === []) {
            throw new InvalidArgumentException('Not a CODA file.');
        }

        return $statements;
    }

    /**
     * The account of record 1 by its structure: 0 Belgian number, 1 foreign, 2 Belgian IBAN, 3 foreign IBAN.
     *
     * @param  callable(int, int): string  $field
     * @return array{0: string, 1: string}
     */
    protected function account(string $structure, callable $field): array
    {
        return match ($structure) {
            '0' => [self::belgianIban(trim($field(6, 17))), trim($field(19, 21)) ?: 'EUR'],
            '2' => [trim($field(6, 21)), trim($field(40, 42)) ?: 'EUR'],
            default => [preg_replace('/\s+/', '', trim($field(6, 39))), trim($field(40, 42)) ?: 'EUR'],
        };
    }

    /**
     * @param  array<string, mixed>  $movement
     * @return array{external_id: string, booking_date: string, value_date: ?string, amount: float, counterparty_name: ?string, counterparty_iban: ?string, remittance: ?string, structured_reference: ?string}
     */
    protected function movement(array $movement, string $iban): array
    {
        $communication = (string) $movement['communication'];
        $structured = null;

        // Structured communication: type 101 (or 102) followed by the 12 digits.
        if ($movement['structured'] && preg_match('/^10[12](\d{12})/', $communication, $match)) {
            $structured = $match[1];
            $remittance = '+++'.substr($structured, 0, 3).'/'.substr($structured, 3, 4).'/'.substr($structured, 7, 5).'+++';
        } else {
            $remittance = trim(preg_replace('/\s+/', ' ', $communication));
        }

        $booking = $movement['entry_date'] ?? $movement['value_date'] ?? now()->toDateString();

        return [
            'external_id' => 'coda:'.sha1(implode('|', [$iban, $movement['statement'], $movement['sequence'], $booking, $movement['bank_reference'], $movement['amount']])),
            'booking_date' => $booking,
            'value_date' => $movement['value_date'],
            'amount' => round((float) $movement['amount'], 2),
            'counterparty_name' => $movement['counterparty_name'],
            'counterparty_iban' => $movement['counterparty_iban'],
            'remittance' => $remittance !== '' ? $remittance : null,
            'structured_reference' => $structured,
        ];
    }

    protected function date(string $ddmmyy): ?string
    {
        if (! preg_match('/^\d{6}$/', $ddmmyy) || $ddmmyy === '000000') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('dmy', $ddmmyy)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function counterpartyIban(string $value): ?string
    {
        $value = strtoupper(trim($value));

        if (preg_match('/^([A-Z]{2}\d{2}[A-Z0-9]{8,30})/', preg_replace('/\s+/', '', $value), $match)) {
            return substr($match[1], 0, 34);
        }

        return preg_match('/^\d{12}$/', $value) ? self::belgianIban($value) : null;
    }

    /**
     * The IBAN of an old 12-digit Belgian account number.
     */
    public static function belgianIban(string $number): string
    {
        $number = preg_replace('/\D/', '', $number);
        $check = 98 - self::mod97($number.'111400');

        return 'BE'.str_pad((string) $check, 2, '0', STR_PAD_LEFT).$number;
    }

    public static function mod97(string $digits): int
    {
        $remainder = 0;

        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder;
    }
}
