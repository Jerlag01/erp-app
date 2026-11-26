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

    /**
     * A half day as recorded before half days were removed (new ones are always whole days): made a half
     * day after it is created, as an existing record may still be.
     */
    public function legacyHalfDay(): static
    {
        return $this->afterCreating(fn ($model) => $model->update(['half_day' => true]));
    }
}
