<?php

namespace Database\Factories;

use App\Enums\MobileKeyRevokeReason;
use App\Models\MobileKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Keys made here have the secret "secret"; use MobileKey::issue() when a test needs a real one.
 *
 * @extends Factory<MobileKey>
 */
class MobileKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => MobileKey::KEY_PREFIX.Str::random(32),
            'secret_hash' => hash('sha256', 'secret'),
            'device_name' => fake()->randomElement(['Samsung Galaxy S24', 'Google Pixel 8', 'OnePlus 12']),
            'device_id' => (string) Str::uuid(),
            'expires_at' => now()->addMonths(MobileKey::VALID_MONTHS),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subDay()]);
    }

    public function revoked(MobileKeyRevokeReason $reason = MobileKeyRevokeReason::Admin): static
    {
        return $this->state(fn (array $attributes) => ['revoked_at' => now(), 'revoked_reason' => $reason]);
    }
}
