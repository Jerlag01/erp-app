<?php

namespace Tests\Feature;

use App\Actions\Employees\EndEmployment;
use App\Enums\MobileKeyRevokeReason;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\RelationManagers\MobileKeysRelationManager;
use App\Models\Employee;
use App\Models\MobileKey;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['email' => 'jan@example.com', 'locale' => 'en']);
        $this->employee = Employee::factory()->for($user)->create([
            'first_name' => 'Jan',
            'last_name' => 'Peeters',
            'national_number' => '85073003328',
            'bank_account' => 'BE68539007547034',
        ]);
    }

    /**
     * Signs in like the app does and returns the "Authorization" header for the next calls.
     *
     * @return array{Authorization: string}
     */
    protected function signIn(string $deviceId = 'device-1'): array
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jan@example.com',
            'password' => 'password',
            'device_name' => 'Pixel 8',
            'device_id' => $deviceId,
        ])->assertCreated();

        return ['Authorization' => 'Bearer '.$response->json('key').'.'.$response->json('secret')];
    }

    #[Test]
    public function signing_in_issues_a_key_valid_for_a_year_and_stores_only_a_hash_of_the_secret(): void
    {
        $this->freezeTime();

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'jan@example.com', 'password' => 'password', 'device_name' => 'Pixel 8', 'device_id' => 'device-1'])
            ->assertCreated()
            ->assertJsonPath('profile.email', 'jan@example.com')
            ->assertJsonPath('profile.employee.full_name', 'Jan Peeters')
            ->assertJsonPath('expires_at', now()->addYear()->toIso8601String());

        $key = MobileKey::sole();
        $this->assertSame($response->json('key'), $key->key);
        $this->assertSame('Pixel 8', $key->device_name);
        $this->assertNotSame($response->json('secret'), $key->secret_hash);
        $this->assertSame(hash('sha256', $response->json('secret')), $key->secret_hash);
        $this->assertArrayNotHasKey('secret_hash', $key->toArray());
    }

    #[Test]
    public function wrong_credentials_and_inactive_logins_get_no_key(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'jan@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_credentials');

        $this->employee->user->forceFill(['is_active' => false])->save();

        $this->postJson('/api/v1/auth/login', ['email' => 'jan@example.com', 'password' => 'password'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'account_inactive');

        $this->assertSame(0, MobileKey::count());
    }

    #[Test]
    public function the_app_loads_the_employees_own_personal_details_with_the_key(): void
    {
        $headers = $this->signIn();

        $this->getJson('/api/v1/me', $headers)
            ->assertOk()
            ->assertJsonPath('data.email', 'jan@example.com')
            ->assertJsonPath('data.employee.first_name', 'Jan')
            ->assertJsonPath('data.employee.address.city', 'Mechelen')
            ->assertJsonPath('data.employee.national_number', '85.07.30-033.28')
            ->assertJsonPath('data.employee.bank_account', 'BE68 5390 0754 7034')
            ->assertJsonPath('data.employee.employment_category.value', 'worker')
            ->assertJsonPath('data.employee.emergency_contacts.0.name', 'An Peeters')
            ->assertJsonPath('data.employee.has_pending_changes', false)
            ->assertJsonStructure(['meta' => ['key_expires_at']]);

        $this->assertNotNull(MobileKey::sole()->last_used_at);
    }

    #[Test]
    public function requests_without_a_valid_key_are_refused(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'key_invalid');

        $headers = $this->signIn();
        $wrongSecret = ['Authorization' => explode('.', $headers['Authorization'])[0].'.not-the-secret'];

        $this->getJson('/api/v1/me', $wrongSecret)->assertUnauthorized()->assertJsonPath('code', 'key_invalid');
    }

    #[Test]
    public function keys_expire_after_a_year(): void
    {
        $headers = $this->signIn();

        $this->travel(MobileKey::VALID_MONTHS)->months();
        $this->travel(1)->minutes();

        $this->getJson('/api/v1/me', $headers)->assertUnauthorized()->assertJsonPath('code', 'key_expired');
    }

    #[Test]
    public function signing_out_revokes_the_key_and_signing_in_again_on_the_same_device_replaces_it(): void
    {
        $first = $this->signIn('device-1');
        $other = $this->signIn('device-2');
        $second = $this->signIn('device-1');

        $this->getJson('/api/v1/me', $first)->assertUnauthorized()->assertJsonPath('code', 'key_revoked');
        $this->getJson('/api/v1/me', $second)->assertOk();
        $this->assertSame(2, $this->employee->user->mobileKeys()->usable()->count());

        $this->postJson('/api/v1/auth/logout', [], $second)->assertOk();
        $this->getJson('/api/v1/me', $second)->assertUnauthorized()->assertJsonPath('code', 'key_revoked');
        $this->getJson('/api/v1/me', $other)->assertOk();
    }

    #[Test]
    public function keys_are_revoked_when_the_employee_leaves(): void
    {
        $headers = $this->signIn();

        app(EndEmployment::class)->handle($this->employee, today()->toDateString());

        $this->assertSame(MobileKeyRevokeReason::UserInactive, MobileKey::sole()->revoked_reason);
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized()->assertJsonPath('code', 'account_inactive');

        // Hiring again switches the login back on, but the app has to sign in again.
        app(EndEmployment::class)->rehire($this->employee->fresh());
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
    }

    #[Test]
    public function keys_are_revoked_when_the_login_is_switched_off_or_removed(): void
    {
        $headers = $this->signIn();

        $this->employee->user->update(['is_active' => false]);
        $this->assertFalse(MobileKey::sole()->isUsable());
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized()->assertJsonPath('code', 'account_inactive');

        $this->employee->user->delete();
        $this->assertSame(0, MobileKey::count());
    }

    #[Test]
    public function administrators_see_and_revoke_the_devices_of_a_user(): void
    {
        $headers = $this->signIn();
        $key = MobileKey::sole();
        $user = $this->employee->user;

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MobileKeysRelationManager::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
            ->assertCanSeeTableRecords([$key])
            ->callTableAction('revoke', $key);

        $this->assertSame(MobileKeyRevokeReason::Admin, $key->fresh()->revoked_reason);
        $this->getJson('/api/v1/me', $headers)->assertUnauthorized()->assertJsonPath('code', 'key_revoked');
    }
}
