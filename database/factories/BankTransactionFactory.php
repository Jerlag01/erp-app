<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransaction>
 */
class BankTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'external_id' => fake()->unique()->uuid(),
            'source' => BankAccount::CODA,
            'booking_date' => today(),
            'amount' => 100,
            'counterparty_name' => fake()->company(),
            'remittance' => null,
        ];
    }
}
