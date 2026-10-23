<?php

namespace Database\Factories;

use App\Enums\AsbuiltStatus;
use App\Models\Asbuilt;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asbuilt>
 */
class AsbuiltFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'status' => AsbuiltStatus::InProgress,
        ];
    }
}
