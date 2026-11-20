<?php

namespace Database\Factories;

use App\Models\BtvControl;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BtvControl>
 */
class BtvControlFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'requested_on' => '2026-11-02',
        ];
    }
}
