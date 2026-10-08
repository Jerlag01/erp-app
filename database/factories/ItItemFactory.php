<?php

namespace Database\Factories;

use App\Enums\ItItemKind;
use App\Models\ItItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItItem>
 */
class ItItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => ItItemKind::Accessory,
            'name' => fake()->randomElement(['USB-C dock', 'Draadloze muis', 'Headset']).' '.fake()->unique()->randomNumber(4),
            'quantity' => 10,
        ];
    }

    public function consumable(): static
    {
        return $this->state(fn (array $attributes) => ['kind' => ItItemKind::Consumable, 'name' => 'Toner '.fake()->unique()->randomNumber(4)]);
    }

    public function component(): static
    {
        return $this->state(fn (array $attributes) => ['kind' => ItItemKind::Component, 'name' => 'RAM 16 GB '.fake()->unique()->randomNumber(4)]);
    }
}
