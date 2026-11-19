<?php

namespace Database\Factories;

use App\Enums\Bank;
use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Zichtrekening',
            'iban' => 'BE'.fake()->unique()->numerify('##############'),
            'bank' => Bank::Kbc,
            'provider' => BankAccount::CODA,
            'currency' => 'EUR',
            'is_active' => true,
        ];
    }

    public function ponto(string $externalId = 'acc-1'): static
    {
        return $this->state(['provider' => BankAccount::PONTO, 'external_id' => $externalId]);
    }
}
