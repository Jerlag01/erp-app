<?php

namespace Database\Factories;

use App\Enums\GpwStatus;
use App\Models\GpwRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GpwRequest>
 */
class GpwRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = today()->addWeeks(2);

        return [
            'project_id' => Project::factory(),
            'requested_by' => User::factory(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'locale' => 'nl',
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addDays(2)->toDateString(),
            'status' => GpwStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => GpwStatus::Approved, 'decided_at' => now(), 'decided_by_name' => 'Klant']);
    }
}
