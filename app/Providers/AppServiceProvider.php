<?php

namespace App\Providers;

use App\Models\ClientUser;
use App\Support\Modules;
use App\Support\Settings;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(Modules::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // When a client portal user signs in, note it (shown to the administrators).
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof ClientUser) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        // Mail settings from the admin panel override .env once the mailer is first used.
        $this->app->afterResolving('mail.manager', fn () => $this->app->make(Settings::class)->applyMailConfig());

        // Livewire accepts temporary uploads up to 250 MB (plans, drawings and the app's APK are large);
        // every upload field still sets its own, usually lower, limit.
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:256000']]);
    }
}
