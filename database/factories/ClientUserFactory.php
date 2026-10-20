<?php

namespace Database\Factories;

use App\Models\ClientUser;
use App\Support\ClientPortal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientUser>
 */
class ClientUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'company' => 'Proximus',
            'password' => 'password',
            'locale' => 'nl',
            'is_active' => true,
            'permissions' => [ClientPortal::PROJECTS],
            'project_categories' => ClientPortal::CATEGORIES,
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    public function withPermissions(array $permissions): static
    {
        return $this->state(['permissions' => $permissions]);
    }
}
