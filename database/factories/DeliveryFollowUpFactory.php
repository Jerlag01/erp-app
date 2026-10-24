<?php

namespace Database\Factories;

use App\Models\BoardSupplier;
use App\Models\DeliveryFollowUp;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryFollowUp>
 */
class DeliveryFollowUpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => DeliveryFollowUp::ENERSYS,
            'project_id' => fn (): int => Project::factory()->inCategory('605')->create()->id,
            'po_number' => '45'.fake()->numerify('########'),
            'delivery_date' => '2026-11-10',
        ];
    }

    public function board(string $supplier = 'ELP'): static
    {
        return $this->state(fn (): array => [
            'kind' => DeliveryFollowUp::BOARD,
            'board_supplier_id' => BoardSupplier::query()->where('name', $supplier)->value('id'),
            'project_id' => Project::factory()->inCategory('610')->create()->id,
        ]);
    }
}
