<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\WorkSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_category_id' => fn (): int => ProjectCategory::query()->where('code', '604')->value('id') ?? ProjectCategory::factory()->create()->id,
            'work_site_id' => WorkSite::factory(),
            'status' => ProjectStatus::Offer,
        ];
    }

    public function inCategory(string $code): static
    {
        return $this->state(fn (): array => ['project_category_id' => ProjectCategory::query()->where('code', $code)->value('id')]);
    }
}
