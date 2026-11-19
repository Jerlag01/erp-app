<?php

namespace App\Actions\Banking;

use App\Enums\BankTransactionStatus;
use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Checks the bank movements against the invoices: money in with the structured communication
 * (or the number) and the exact amount of an open invoice marks it paid on the booking date;
 * money out does the same for an incoming invoice. Anything less certain is left to a person,
 * with suggestions. Reading only: no payment is ever made from here.
 */
class MatchBankTransactions
{
    /**
     * Tries every unmatched movement (or the given ones) and returns how many were linked.
     *
     * @param  iterable<BankTransaction>|null  $transactions
     */
    public function run(?iterable $transactions = null): int
    {
        $matched = 0;
        $transactions ??= BankTransaction::query()->where('status', BankTransactionStatus::Unmatched)->orderBy('booking_date')->lazy();

        foreach ($transactions as $transaction) {
            if ($transaction->status === BankTransactionStatus::Unmatched && ($target = $this->find($transaction)) !== null) {
                $this->link($transaction, $target);
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * The one document this movement certainly paid, or null.
     */
    public function find(BankTransaction $transaction): Invoice|Expense|null
    {
        $amount = abs((float) $transaction->amount);

        if ($transaction->isIncoming()) {
            $candidates = $this->openInvoices()->get()->filter(fn (Invoice $invoice): bool => $this->sameAmount((float) $invoice->total, $amount));

            if ($transaction->structured_reference !== null) {
                $byReference = $candidates->filter(fn (Invoice $invoice): bool => self::digits($invoice->structured_reference) === $transaction->structured_reference);

                if ($byReference->count() === 1) {
                    return $byReference->first();
                }
            }

            $byNumber = $candidates->filter(fn (Invoice $invoice): bool => filled($invoice->number) && self::mentions($transaction->remittance, (string) $invoice->number));

            return $byNumber->count() === 1 ? $byNumber->first() : null;
        }

        $candidates = $this->openExpenses()->get()->filter(fn (Expense $expense): bool => $this->sameAmount((float) $expense->total, $amount));

        if ($transaction->structured_reference !== null) {
            $byReference = $candidates->filter(fn (Expense $expense): bool => self::digits($expense->payment_reference) === $transaction->structured_reference);

            if ($byReference->count() === 1) {
                return $byReference->first();
            }
        }

        $byNumber = $candidates->filter(fn (Expense $expense): bool => filled($expense->supplier_invoice_number) && self::mentions($transaction->remittance, (string) $expense->supplier_invoice_number));

        return $byNumber->count() === 1 ? $byNumber->first() : null;
    }

    /**
     * Open documents a person can link the movement to, the most likely first (same amount, then
     * the communication or name in the text).
     *
     * @return array<string, string> "invoice:12" => label
     */
    public function options(BankTransaction $transaction): array
    {
        $amount = abs((float) $transaction->amount);
        $text = mb_strtolower($transaction->remittance.' '.$transaction->counterparty_name);

        if ($transaction->isIncoming()) {
            $documents = $this->openInvoices()->with('client')->get()->map(fn (Invoice $invoice): array => [
                'key' => 'invoice:'.$invoice->id,
                'label' => $invoice->number.' · '.$invoice->client?->label().' · '.Invoice::money($invoice->total),
                'score' => ($this->sameAmount((float) $invoice->total, $amount) ? 4 : 0)
                    + (self::digits($invoice->structured_reference) === $transaction->structured_reference && $transaction->structured_reference ? 4 : 0)
                    + ($invoice->client && str_contains($text, mb_strtolower((string) $invoice->client->name)) ? 2 : 0),
                'date' => $invoice->issue_date?->timestamp ?? 0,
            ]);
        } else {
            $documents = $this->openExpenses()->get()->map(fn (Expense $expense): array => [
                'key' => 'expense:'.$expense->id,
                'label' => $expense->supplier_name.' · '.($expense->supplier_invoice_number ?: '—').' · '.Invoice::money($expense->total),
                'score' => ($this->sameAmount((float) $expense->total, $amount) ? 4 : 0)
                    + (self::digits($expense->payment_reference) === $transaction->structured_reference && $transaction->structured_reference ? 4 : 0)
                    + (str_contains($text, mb_strtolower($expense->supplier_name)) ? 2 : 0),
                'date' => $expense->invoice_date?->timestamp ?? 0,
            ]);
        }

        return $documents
            ->sortBy([['score', 'desc'], ['date', 'desc']])
            ->take(200)
            ->mapWithKeys(fn (array $document): array => [$document['key'] => ($document['score'] >= 4 ? '★ ' : '').$document['label']])
            ->all();
    }

    /**
     * Links a movement to "invoice:ID" or "expense:ID" (a person's choice from options()).
     */
    public function linkByKey(BankTransaction $transaction, string $key, ?User $user = null): void
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, null);

        $target = match ($type) {
            'invoice' => $transaction->isIncoming() ? $this->openInvoices()->find($id) : null,
            'expense' => $transaction->isIncoming() ? null : $this->openExpenses()->find($id),
            default => null,
        };

        if ($target === null) {
            throw new InvalidArgumentException('This document can not be linked to the movement.');
        }

        $this->link($transaction, $target, $user);
    }

    /**
     * Marks the document paid on the booking date and remembers the link.
     */
    public function link(BankTransaction $transaction, Invoice|Expense $target, ?User $user = null): void
    {
        DB::transaction(function () use ($transaction, $target, $user): void {
            if ($target instanceof Invoice) {
                $target->forceFill(['status' => InvoiceStatus::Paid, 'paid_on' => $transaction->booking_date])->save();
            } else {
                $target->forceFill(['paid_on' => $transaction->booking_date])->save();
            }

            $transaction->forceFill([
                'status' => BankTransactionStatus::Matched,
                'matchable_type' => $target->getMorphClass(),
                'matchable_id' => $target->getKey(),
                'matched_by' => $user?->id,
                'matched_at' => now(),
            ])->save();
        });
    }

    /**
     * Undoes a link: the document is open again (unless another movement also paid it).
     */
    public function unlink(BankTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $target = $transaction->matchable;

            $transaction->forceFill(['status' => BankTransactionStatus::Unmatched, 'matchable_type' => null, 'matchable_id' => null, 'matched_by' => null, 'matched_at' => null])->save();

            if (! $target instanceof Model || BankTransaction::query()->whereMorphedTo('matchable', $target)->exists()) {
                return;
            }

            if ($target instanceof Invoice && $target->isPaid()) {
                $target->forceFill(['status' => $target->sent_at ? InvoiceStatus::Sent : InvoiceStatus::Final, 'paid_on' => null])->save();
            } elseif ($target instanceof Expense) {
                $target->forceFill(['paid_on' => null])->save();
            }
        });
    }

    public function ignore(BankTransaction $transaction, ?User $user = null): void
    {
        $transaction->forceFill(['status' => BankTransactionStatus::Ignored, 'matched_by' => $user?->id, 'matched_at' => now()])->save();
    }

    /**
     * @return Builder<Invoice>
     */
    protected function openInvoices(): Builder
    {
        return Invoice::query()->where('type', InvoiceType::Invoice)->whereIn('status', [InvoiceStatus::Final, InvoiceStatus::Sent]);
    }

    /**
     * @return Builder<Expense>
     */
    protected function openExpenses(): Builder
    {
        return Expense::query()->where('is_credit_note', false)->whereNull('paid_on')->where('status', '!=', ExpenseStatus::Rejected);
    }

    protected function sameAmount(float $a, float $b): bool
    {
        return abs($a - $b) < 0.005;
    }

    /**
     * Only the digits of a structured communication ("+++123/…+++" → "123…"), or null.
     */
    public static function digits(?string $reference): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $reference);

        return strlen($digits) === 12 ? $digits : null;
    }

    /**
     * Whether a text names a document number as a whole word (case and spacing ignored).
     */
    protected static function mentions(?string $text, string $number): bool
    {
        $characters = mb_str_split(strtoupper(preg_replace('/\s+/', '', $number)));

        if ($characters === []) {
            return false;
        }

        // The number may be written with spaces in it ("2026 - 0012").
        $pattern = implode('\s*', array_map(fn (string $character): string => preg_quote($character, '/'), $characters));

        return preg_match('/(?<![A-Z0-9])'.$pattern.'(?![A-Z0-9])/u', strtoupper((string) $text)) === 1;
    }

    /**
     * @return Collection<int, BankTransaction>
     */
    public static function forDocument(Model $document): Collection
    {
        return BankTransaction::query()->whereMorphedTo('matchable', $document)->with('account')->get();
    }
}
