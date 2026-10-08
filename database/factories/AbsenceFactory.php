<?php

namespace Database\Factories;

use App\Enums\AbsenceType;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Absence>
 */
class AbsenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => AbsenceType::Medical,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-14',
        ];
    }

    public function ofType(AbsenceType $type): static
    {
        return $this->state(['type' => $type]);
    }
}
