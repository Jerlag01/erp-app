<?php

namespace Database\Factories;

use App\Models\WorkSiteArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSiteArea>
 */
class WorkSiteAreaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Area '.fake()->unique()->city(),
        ];
    }
}
