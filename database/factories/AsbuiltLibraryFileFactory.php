<?php

namespace Database\Factories;

use App\Enums\AsbuiltCategory;
use App\Models\AsbuiltLibraryFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AsbuiltLibraryFile>
 */
class AsbuiltLibraryFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => AsbuiltCategory::TechnicalDocuments,
            'title' => fake()->words(3, true),
            'path' => 'asbuilt-library/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'size' => 1024,
        ];
    }
}
