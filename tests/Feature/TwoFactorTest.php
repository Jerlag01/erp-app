<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ClientUsers\Pages\ManageClientUsers;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Models\ClientUser;
use App\Models\Role;
use App\Models\User;
use Filament\Auth\MultiFactor\Email\Notifications\VerifyEmailAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_panel_offers_an_authenticator_app_and_email_codes(): void
    {
        foreach (['app', 'admin', 'client'] as $panel) {
            $this->assertCount(2, Filament::getPanel($panel)->getMultiFactorAuthenticationProviders(), $panel);
        }

        // The secret is stored encrypted, also for client portal users.
        $client = ClientUser::factory()->create();
        $client->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->assertNotSame('JBSWY3DPEHPK3PXP', DB::table('client_users')->where('id', $client->id)->value('app_authentication_secret'));
        $this->assertSame('JBSWY3DPEHPK3PXP', $client->fresh()->getAppAuthenticationSecret());
        $this->assertTrue($client->fresh()->hasTwoFactor());
    }

    #[Test]
    public function the_app_login_asks_for_the_code_of_the_authenticator_app_or_a_recovery_code(): void
    {
        $google = app(Google2FA::class);
        $secret = $google->generateSecretKey();
        $user = User::factory()->inTeam()->create(['email' => 'jan@power.example', 'password' => 'Geheim-wachtwoord-1']);
        $user->saveAppAuthenticationSecret($secret);
        $user->saveAppAuthenticationRecoveryCodes([Hash::make('herstel-1234')]);
        $login = fn (array $extra = []) => $this->postJson('/api/v1/auth/login', ['email' => 'jan@power.example', 'password' => 'Geheim-wachtwoord-1', ...$extra]);

        $login()->assertUnprocessable()->assertJsonPath('code', 'two_factor_required')->assertJsonPath('methods', ['app']);
        $login(['code' => '000000'])->assertUnprocessable()->assertJsonPath('code', 'two_factor_invalid');
        $login(['code' => $google->getCurrentOtp($secret)])->assertCreated()->assertJsonStructure(['key', 'secret']);

        // A recovery code works once.
        $login(['code' => 'herstel-1234'])->assertCreated();
        $login(['code' => 'herstel-1234'])->assertUnprocessable();
    }

    #[Test]
    public function the_app_login_mails_a_code_to_users_with_email_codes(): void
    {
        Notification::fake();
        $user = User::factory()->inTeam()->create(['email' => 'piet@power.example', 'password' => 'Geheim-wachtwoord-1']);
        $user->toggleEmailAuthentication(true);
        $login = fn (array $extra = []) => $this->postJson('/api/v1/auth/login', ['email' => 'piet@power.example', 'password' => 'Geheim-wachtwoord-1', ...$extra]);

        $login()->assertUnprocessable()->assertJsonPath('methods', ['email']);

        $code = null;
        Notification::assertSentTo($user, VerifyEmailAuthentication::class, function (VerifyEmailAuthentication $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        $login(['code' => $code])->assertCreated();
        // Used once.
        $login(['code' => $code])->assertUnprocessable();

        // Without two-step verification nothing changes.
        User::factory()->inTeam()->create(['email' => 'an@power.example', 'password' => 'Geheim-wachtwoord-1']);
        $this->postJson('/api/v1/auth/login', ['email' => 'an@power.example', 'password' => 'Geheim-wachtwoord-1'])->assertCreated();
    }

    #[Test]
    public function only_administrators_with_the_ticked_permission_can_reset_two_step_verification(): void
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->inTeam()->create();
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $client = ClientUser::factory()->create();
        $client->toggleEmailAuthentication(true);

        // An administrator without the ticked permission does not see it.
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])->assertActionHidden('resetTwoFactor');

        $this->actingAs(User::factory()->create(['role_id' => Role::factory()->create(['is_admin' => true, 'permissions' => ['security' => ['reset_two_factor']]])->id]));
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertActionVisible('resetTwoFactor')
            ->callAction('resetTwoFactor');
        $this->assertFalse($user->fresh()->hasTwoFactor());

        Livewire::test(ManageClientUsers::class)->callTableAction('resetTwoFactor', $client);
        $this->assertFalse($client->fresh()->hasTwoFactor());

        // Nothing to reset: hidden.
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])->assertActionHidden('resetTwoFactor');
    }
}
