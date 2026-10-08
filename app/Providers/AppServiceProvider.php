<?php

namespace App\Providers;

use App\Support\Modules;
use App\Support\Settings;
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
        // Mail settings from the admin panel override .env once the mailer is first used.
        $this->app->afterResolving('mail.manager', fn () => $this->app->make(Settings::class)->applyMailConfig());

        // Livewire accepts temporary uploads up to 100 MB (plans and drawings are large); every
        // upload field still sets its own, usually lower, limit.
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:102400']]);
    }
}
