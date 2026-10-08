<?php

use App\Support\Modules;
use App\Support\Settings;

if (! function_exists('settings')) {
    /**
     * Get the settings service, or a single value by dot-notation key.
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}

if (! function_exists('modules')) {
    /**
     * Get the module switchboard (fleet, teams, assets).
     */
    function modules(): Modules
    {
        return app(Modules::class);
    }
}
