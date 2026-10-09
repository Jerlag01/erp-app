<?php

namespace Database\Factories;

use App\Enums\PlanningKind;
use App\Models\PlanningItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanningItem>
 */
class PlanningItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => '2026-10-12',
            'start_time' => '07:30:00',
            'end_time' => '16:00:00',
            'kind' => PlanningKind::Warehouse,
            'description' => fake()->sentence(),
            'tasks' => [['text' => 'Rekken opruimen', 'done' => false]],
        ];
    }

    public function forProject(?Project $project = null): static
    {
        return $this->state(fn (): array => [
            'kind' => PlanningKind::Project,
            'project_id' => $project?->id ?? Project::factory(),
            'description' => null,
            'tasks' => null,
        ]);
    }
}
