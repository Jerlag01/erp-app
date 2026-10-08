<?php

namespace Database\Factories;

use App\Enums\VacationStatus;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VacationRequest>
 */
class VacationRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'start_date' => '2026-11-16',
            'end_date' => '2026-11-20',
            'half_day' => false,
            'status' => VacationStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => VacationStatus::Approved]);
    }
}
