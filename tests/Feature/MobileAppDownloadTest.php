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
}
