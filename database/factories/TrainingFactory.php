<?php

namespace Database\Factories;

use App\Models\Training;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Training>
 */
class TrainingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'VCA VOL',
            'start_date' => '2026-10-14',
            'end_date' => '2026-10-14',
            'company' => 'Securex',
            'street' => 'Kortrijksesteenweg',
            'house_number' => '1',
            'postal_code' => '9000',
            'city' => 'Gent',
            'contact_phone' => '09 123 45 67',
            'contact_email' => 'opleidingen@example.com',
        ];
    }
}
