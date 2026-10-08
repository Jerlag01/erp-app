<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => Role::factory(),
            'job_title' => fake()->jobTitle(),
            'locale' => 'nl',
            'is_active' => true,
        ];
    }

    /**
     * A user with an administrator role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::factory()->admin(),
        ]);
    }

    /**
     * A user whose role grants exactly these permissions, e.g. ['vehicles' => ['view']].
     *
     * @param  array<string, list<string>>  $permissions
     */
    public function withPermissions(array $permissions): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::factory()->state(['permissions' => $permissions]),
        ]);
    }

    /**
     * A member of the given team, or of a new one. Non-administrators need a team,
     * otherwise they act as guests without permissions.
     */
    public function inTeam(?Team $team = null): static
    {
        return $this->afterCreating(function (User $user) use ($team): void {
            $user->teams()->attach($team ?? Team::factory()->create());
            $user->flushTeamIds();
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
