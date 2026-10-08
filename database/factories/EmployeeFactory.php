<?php

namespace Database\Factories;

use App\Enums\ContractTerm;
use App\Enums\EmploymentCategory;
use App\Models\Country;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'street' => 'Stationsstraat',
            'house_number' => (string) fake()->numberBetween(1, 200),
            'postal_code' => '2800',
            'city' => 'Mechelen',
            'country_id' => fn () => Country::belgiumId(),
            'private_phone' => '0470 12 34 56',
            'employment_date' => now()->subYears(2)->toDateString(),
            'employment_category' => EmploymentCategory::Worker,
            'contract_term' => ContractTerm::Indefinite,
            'emergency1_first_name' => 'An',
            'emergency1_last_name' => 'Peeters',
            'emergency1_phone' => '0475 11 22 33',
            'emergency1_relation' => 'Partner',
        ];
    }

    public function left(): static
    {
        return $this->state(['left_on' => now()->subMonth()->toDateString()]);
    }
}
