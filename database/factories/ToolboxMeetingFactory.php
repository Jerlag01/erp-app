<?php

namespace Database\Factories;

use App\Models\ToolboxMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolboxMeeting>
 */
class ToolboxMeetingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Heffen en tillen',
            'subject_nl' => 'Heffen en tillen',
            'subject_fr' => 'Levage de charges lourdes',
            'location' => 'NV SA Power Installation',
            'meeting_month' => '2026-09-01',
            'instructors' => ['Rudy Cools', 'Alain Dubois'],
            'body' => '<h2>Veilig tillen</h2><p>Buig door je knieën en houd de rug recht.</p>',
            'attachments' => [],
            'attachment_names' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(['published_at' => now()]);
    }
}
