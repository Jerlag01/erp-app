<?php

namespace App\Support;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceType;
use App\Models\Expense;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Totals per month of invoices sent, credit notes and incoming invoices (expenses), with or
 * without VAT. Drafts and rejected expenses do not count; a supplier's credit note lowers the
 * expenses.
 */
class FinanceFigures
{
    /**
     * @return array<string, array{month: CarbonImmutable, invoices: float, credit_notes: float, expenses: float, invoice_count: int, credit_note_count: int, expense_count: int}>
     */
    public static function monthly(string $from, string $until, bool $withVat): array
    {
        $start = CarbonImmutable::parse($from)->startOfMonth();
        $end = CarbonImmutable::parse($until)->startOfMonth();
        $months = [];

        foreach (CarbonPeriod::create($start, '1 month', $end) as $month) {
            $months[$month->format('Y-m')] = [
                'month' => CarbonImmutable::instance($month),
                'invoices' => 0.0, 'credit_notes' => 0.0, 'expenses' => 0.0,
                'invoice_count' => 0, 'credit_note_count' => 0, 'expense_count' => 0,
            ];
        }

        $column = $withVat ? 'total' : 'subtotal';

        Invoice::query()->booked()
            ->whereBetween('issue_date', [$from, $until])
            ->get(['type', 'issue_date', 'subtotal', 'total'])
            ->each(function (Invoice $invoice) use (&$months, $column): void {
                $key = $invoice->issue_date->format('Y-m');

                if (! isset($months[$key])) {
                    return;
                }

                $field = $invoice->type === InvoiceType::CreditNote ? 'credit_notes' : 'invoices';
                $months[$key][$field] += (float) $invoice->{$column};
                $months[$key][$field === 'invoices' ? 'invoice_count' : 'credit_note_count']++;
            });

        Expense::query()
            ->where('status', '!=', ExpenseStatus::Rejected)
            ->whereBetween('invoice_date', [$from, $until])
            ->get(['invoice_date', 'subtotal', 'total', 'is_credit_note'])
            ->each(function (Expense $expense) use (&$months, $withVat): void {
                $key = $expense->invoice_date->format('Y-m');

                if (isset($months[$key])) {
                    $months[$key]['expenses'] += $expense->signedTotal($withVat);
                    $months[$key]['expense_count']++;
                }
            });

        return $months;
    }

    /**
     * @param  array<string, array<string, mixed>>  $months
     * @return array{invoices: float, credit_notes: float, income: float, expenses: float, result: float}
     */
    public static function totals(array $months): array
    {
        $invoices = round(array_sum(array_column($months, 'invoices')), 2);
        $credit = round(array_sum(array_column($months, 'credit_notes')), 2);
        $expenses = round(array_sum(array_column($months, 'expenses')), 2);

        return [
            'invoices' => $invoices,
            'credit_notes' => $credit,
            'income' => round($invoices - $credit, 2),
            'expenses' => $expenses,
            'result' => round($invoices - $credit - $expenses, 2),
        ];
    }

    /**
     * Change against the comparison value in percent, or null when there is nothing to compare with.
     */
    public static function change(float $current, float $previous): ?float
    {
        return abs($previous) < 0.005 ? null : round(($current - $previous) / abs($previous) * 100, 1);
    }
}
