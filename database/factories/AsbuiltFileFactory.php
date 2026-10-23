<?php

namespace Database\Factories;

use App\Enums\AsbuiltCategory;
use App\Models\Asbuilt;
use App\Models\AsbuiltFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AsbuiltFile>
 */
class AsbuiltFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asbuilt_id' => Asbuilt::factory(),
            'category' => AsbuiltCategory::Plans->value,
            'path' => 'asbuilt/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'size' => 1024,
        ];
    }
}
