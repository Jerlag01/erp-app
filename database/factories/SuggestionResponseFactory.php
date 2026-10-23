<?php

namespace Database\Factories;

use App\Models\Suggestion;
use App\Models\SuggestionResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuggestionResponse>
 */
class SuggestionResponseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'suggestion_id' => Suggestion::factory(),
            'user_id' => User::factory(),
            'author_name' => fake()->name(),
            'from_admin' => false,
            'body' => fake()->sentence(),
            'photos' => [],
        ];
    }
}
