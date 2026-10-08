<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Settings;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_administrator_can_change_the_site_name_modules_and_mail_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(Settings::class)
            ->fillForm([
                'general.site_name' => 'Jacobs ERP',
                'modules.fleet' => false,
                'mail.mailer' => 'smtp',
                'mail.host' => 'smtp.example.com',
                'mail.port' => 587,
                'mail.password' => 'secret',
                'appearance.accent' => '#123456',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        settings()->flush();
        $this->assertSame('Jacobs ERP', settings()->siteName());
        $this->assertFalse(modules()->fleet());
        $this->assertTrue(modules()->teams());
        $this->assertSame('secret', Crypt::decryptString(settings('mail.password')));
        $this->assertSame('#123456', settings()->color('accent'));

        $this->get('/admin')->assertSee('Jacobs ERP');
    }

    #[Test]
    public function an_empty_mail_password_keeps_the_stored_one(): void
    {
        settings()->save('mail', [...settings('mail'), 'password' => Crypt::encryptString('keep-me')]);
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(Settings::class)
            ->assertSet('data.mail.password', null)
            ->call('save');

        settings()->flush();
        $this->assertSame('keep-me', Crypt::decryptString(settings('mail.password')));
    }

    #[Test]
    public function mail_settings_override_the_env_configuration(): void
    {
        settings()->save('mail', [...settings('mail'), 'mailer' => 'smtp', 'host' => 'mail.jl-tech.be', 'from_address' => 'erp@jl-tech.be']);

        settings()->applyMailConfig();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.jl-tech.be', config('mail.mailers.smtp.host'));
        $this->assertSame('erp@jl-tech.be', config('mail.from.address'));
    }
}
