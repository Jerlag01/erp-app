<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Settings;
use App\Models\User;
use App\Support\MobileApp;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileAppDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected const ANDROID = 'Mozilla/5.0 (Linux; Android 16; CPH2581) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Mobile Safari/537.36';

    protected const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    protected function upload(string $version): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        // Like in the form: the current file is removed, then the new one uploaded.
        Livewire::test(Settings::class)
            ->fillForm(['mobile_app.apk' => []])
            ->fillForm([
                'mobile_app.apk' => UploadedFile::fake()->create("pi-erp-{$version}.apk", 500, 'application/vnd.android.package-archive'),
                'mobile_app.version' => $version,
                'mobile_app.prompt' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    #[Test]
    public function the_apk_is_uploaded_in_the_settings_and_a_new_upload_replaces_it(): void
    {
        $this->upload('1.0.0');
        $first = settings()->get('mobile_app.apk');
        Storage::disk('local')->assertExists($first);
        $this->assertSame('pi-erp-1.0.0.apk', settings()->get('mobile_app.apk_name'));
        $this->assertNotNull(settings()->get('mobile_app.uploaded_at'));

        $this->upload('1.1.0');
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists(settings()->get('mobile_app.apk'));
        $this->assertSame('1.1.0', app(MobileApp::class)->version());
    }

    #[Test]
    public function only_signed_in_active_users_download_the_app(): void
    {
        $this->upload('1.2.0');
        auth()->logout();

        $this->get(route('mobile-app.download'))->assertRedirect();

        $employee = User::factory()->withPermissions([])->inTeam()->create();
        $this->actingAs($employee)->get(route('mobile-app.download'))
            ->assertOk()
            ->assertDownload('erp-1.2.0.apk')
            ->assertHeader('content-type', 'application/vnd.android.package-archive');

        $employee->update(['is_active' => false]);
        $this->actingAs($employee->fresh())->get(route('mobile-app.download'))->assertForbidden();
    }

    #[Test]
    public function the_app_is_suggested_after_signing_in_on_an_android_phone(): void
    {
        $employee = User::factory()->withPermissions([])->inTeam()->create();

        // Nothing to download yet: no suggestion and no menu item.
        $this->actingAs($employee)->withHeader('User-Agent', self::ANDROID)->get('/')->assertOk()->assertDontSee('data-mobile-app-prompt', false);

        $this->upload('1.2.0');

        $this->actingAs($employee)->withHeader('User-Agent', self::ANDROID)->get('/')
            ->assertOk()
            ->assertSee('data-mobile-app-prompt', false)
            ->assertSee(route('mobile-app.download'), false)
            ->assertSee('Versie 1.2.0');

        // Not on a computer, and not when it is switched off.
        $this->actingAs($employee)->withHeader('User-Agent', self::DESKTOP)->get('/')->assertOk()->assertDontSee('data-mobile-app-prompt', false);

        settings()->save('mobile_app', [...settings('mobile_app'), 'prompt' => false]);
        $this->actingAs($employee)->withHeader('User-Agent', self::ANDROID)->get('/')->assertOk()->assertDontSee('data-mobile-app-prompt', false);
    }

    #[Test]
    public function the_message_is_set_per_language_and_the_employee_can_dismiss_it(): void
    {
        $this->upload('1.2.0');
        settings()->save('mobile_app', [...settings('mobile_app'), 'message_nl' => 'Installeer de app vóór maandag.', 'message_fr' => "Installez l'app avant lundi."]);
        $jan = User::factory()->withPermissions([])->inTeam()->create(['locale' => 'nl']);
        $marie = User::factory()->withPermissions([])->inTeam()->create(['locale' => 'fr']);

        $this->actingAs($jan)->withHeader('User-Agent', self::ANDROID)->get('/')
            ->assertSee('Installeer de app vóór maandag.')
            ->assertSee('Niet meer tonen')
            // Readable in light and dark mode: the card sets its own text colour.
            ->assertSee('.dark .erp-app-prompt__card { background: #111827; color: #f1f5f9; }', false);
        $this->actingAs($marie)->withHeader('User-Agent', self::ANDROID)->get('/')->assertSee("Installez l'app avant lundi.");

        // "Don't show again": gone for this user, on every device…
        $this->actingAs($jan)->post(route('mobile-app.dismiss'))->assertNoContent();
        $this->assertSame('1.2.0', $jan->fresh()->mobile_app_dismissed_release);
        $this->actingAs($jan->fresh())->withHeader('User-Agent', self::ANDROID)->get('/')->assertDontSee('data-mobile-app-prompt', false);
        $this->actingAs($marie)->withHeader('User-Agent', self::ANDROID)->get('/')->assertSee('data-mobile-app-prompt', false);

        // …until a new version is uploaded.
        $this->upload('1.3.0');
        $this->actingAs($jan->fresh())->withHeader('User-Agent', self::ANDROID)->get('/')->assertSee('data-mobile-app-prompt', false);
    }
}
