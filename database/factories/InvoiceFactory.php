<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\VatRate;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Draft,
            'client_id' => Client::factory(),
            'issue_date' => '2026-10-01',
            'subject' => 'Elektriciteitswerken',
        ];
    }

    /**
     * Adds a line of the given amount (excl. VAT) at the given rate and recalculates.
     */
    public function withLine(float $amount = 1000, VatRate $rate = VatRate::TwentyOne): static
    {
        return $this->afterCreating(function (Invoice $invoice) use ($amount, $rate): void {
            $invoice->lines()->create(['description' => 'Werken', 'quantity' => 1, 'unit_price' => $amount, 'vat_rate' => $rate]);
            $invoice->recalculate();
        });
    }

    /**
     * Finalised (numbered); call after withLine().
     */
    public function finalized(): static
    {
        return $this->afterCreating(fn (Invoice $invoice) => $invoice->finalize());
    }
}
