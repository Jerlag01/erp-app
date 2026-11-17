<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Too Many Attempts" in the app: the limit is per employee, so colleagues on the same Wi-Fi or mobile network
 * (one shared IP address) never use up each other's requests.
 */
class MobileRateLimitTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_app_limit_is_per_employee_and_not_per_ip_address(): void
    {
        $busy = $this->mobileAuth(User::factory()->inTeam()->create());
        $colleague = $this->mobileAuth(User::factory()->inTeam()->create());

        for ($request = 1; $request <= 240; $request++) {
            $this->app['auth']->forgetGuards();
            $this->getJson('/api/v1/me', $busy)->assertOk();
        }

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', $busy)->assertTooManyRequests();

        // Same IP address, another employee: not affected.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', $colleague)->assertOk();
    }

    #[Test]
    public function wrong_passwords_only_block_signing_in_to_that_account(): void
    {
        $other = User::factory()->inTeam()->create(['email' => 'collega@example.com', 'password' => Hash::make('juist-wachtwoord')]);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'iemand@example.com', 'password' => 'fout', 'device_name' => 'Test'])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'iemand@example.com', 'password' => 'fout', 'device_name' => 'Test'])->assertTooManyRequests();
        $this->postJson('/api/v1/auth/login', ['email' => $other->email, 'password' => 'juist-wachtwoord', 'device_name' => 'Test', 'device_id' => 'phone-x'])->assertCreated();
    }
}
