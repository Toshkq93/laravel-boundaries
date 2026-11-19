<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use Toshkq93\Boundaries\BoundariesServiceProvider;

abstract class TestCase extends Orchestra
{
    public const FIXTURES_NAMESPACE = 'Toshkq93\\Boundaries\\Tests\\Fixtures\\Modules';

    protected function getPackageProviders($app): array
    {
        return [
            BoundariesServiceProvider::class,
        ];
    }

    /**
     * Module settings go in with the application config, before the providers
     * are registered (defineEnvironment() runs after that).
     *
     * @param  Application  $app
     */
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('boundaries.path', __DIR__.'/Fixtures/Modules');
        $app['config']->set('boundaries.namespace', self::FIXTURES_NAMESPACE);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
