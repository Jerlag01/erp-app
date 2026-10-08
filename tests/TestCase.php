<?php

namespace Tests;

use App\Support\Modules;
use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but a test database: RefreshDatabase wipes it.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $database = (string) $app['config']->get('database.connections.'.$app['config']->get('database.default').'.database');

        if (! str_ends_with($database, '_test') && $database !== ':memory:') {
            throw new RuntimeException("Tests must run against a *_test database, not [{$database}]. Run them with phpunit.xml.");
        }

        return $app;
    }

    /**
     * Switch modules on or off, e.g. ['fleet' => false].
     *
     * @param  array<string, bool>  $modules
     */
    protected function setModules(array $modules): void
    {
        $settings = app(Settings::class);
        $current = array_intersect_key($settings->get('modules'), array_flip(Modules::ALL));

        $settings->save('modules', [...$current, ...$modules]);
    }
}
