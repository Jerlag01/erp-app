<?php

namespace Database\Factories;

use App\Models\CustomAbsenceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomAbsenceType>
 */
class CustomAbsenceTypeFactory extends Factory
{
    protected $model = CustomAbsenceType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'X'.fake()->unique()->numberBetween(1, 999),
            'name' => 'Opleiding extern',
            'color' => '#0f766e',
            'counting' => CustomAbsenceType::UNWORKED,
            'blocks_planning' => false,
            'is_private' => true,
            'takes_document' => false,
            'is_active' => true,
        ];
    }
}
