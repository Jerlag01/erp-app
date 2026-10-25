<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileAppUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_app_hears_which_version_is_on_the_platform_and_where_to_download_it(): void
    {
        Storage::fake('local');
        $auth = $this->mobileAuth(User::factory()->inTeam()->create());

        // Nothing uploaded: no update.
        $this->getJson('/api/v1/me', $auth)->assertOk()->assertJsonPath('data.app_update', null);

        Storage::disk('local')->put('mobile-app/pi-erp.apk', 'apk');
        settings()->save('mobile_app', [...settings('mobile_app'), 'apk' => 'mobile-app/pi-erp.apk', 'version' => '1.2.0']);

        $this->getJson('/api/v1/me', $auth)
            ->assertJsonPath('data.app_update.version', '1.2.0')
            ->assertJsonPath('data.app_update.url', route('mobile-app.download'));

        // Without a version the phone cannot compare: no update message.
        settings()->save('mobile_app', [...settings('mobile_app'), 'version' => null]);
        $this->getJson('/api/v1/me', $auth)->assertJsonPath('data.app_update', null);
    }
}
