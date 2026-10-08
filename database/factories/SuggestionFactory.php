<?php

namespace Database\Factories;

use App\Enums\SuggestionType;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suggestion>
 */
class SuggestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'submitter_name' => fn (array $attributes) => $attributes['first_name'].' '.$attributes['last_name'],
            'submitted_at' => now(),
            'type' => SuggestionType::Idea,
            'may_be_public' => false,
            'description' => 'Een tweede koffiemachine in de refter.',
        ];
    }
}
