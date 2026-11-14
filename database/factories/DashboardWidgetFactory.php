<?php

namespace Database\Factories;

use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DashboardWidget>
 */
class DashboardWidgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'panel' => 'app',
            'type' => DashboardWidget::COUNTER,
            'title' => fake()->words(2, true),
            'source' => 'projects',
            'config' => ['filters' => [], 'aggregate' => 'count', 'display' => 'number'],
            'column_span' => '1',
        ];
    }
}
