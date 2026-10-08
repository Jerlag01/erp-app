<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_name' => 'Cebeo',
            'supplier_vat' => 'BE0402326408',
            'supplier_invoice_number' => fake()->unique()->numerify('F-######'),
            'invoice_date' => '2026-10-02',
            'subtotal' => 500,
            'vat_total' => 105,
        ];
    }
}
