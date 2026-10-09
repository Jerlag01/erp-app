<?php

namespace Database\Factories;

use App\Enums\InterventionReportStatus;
use App\Models\InterventionReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterventionReport>
 */
class InterventionReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'status' => InterventionReportStatus::Pending,
            'room' => 'Room 1',
            'hours_worked' => 2.5,
            'categories' => ['CONNECT'],
            'executed_at' => now(),
            'closed_by' => User::factory(),
        ];
    }
}
