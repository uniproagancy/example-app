<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mcamara\LaravelLocalization\LaravelLocalization;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $config = $app['config'];

        if ($config->get('database.default') !== 'sqlite' || $config->get('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Tests refuse to run outside the in-memory SQLite database. Run "php artisan optimize:clear" and try again.');
        }

        return $app;
    }

    protected function refreshApplicationWithLocale(string $locale): void
    {
        self::tearDown();
        putenv(LaravelLocalization::ENV_ROUTE_KEY.'='.$locale);
        self::setUp();
    }

    protected function tearDown(): void
    {
        putenv(LaravelLocalization::ENV_ROUTE_KEY);
        parent::tearDown();
    }
}
