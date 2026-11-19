<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Tests\Feature;

use Toshkq93\Boundaries\Modules\ModuleRegistry;
use Toshkq93\Boundaries\ModuleServiceProvider;
use Toshkq93\Boundaries\Tests\TestCase;

class AutoRegisterDisabledTest extends TestCase
{
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('boundaries.auto_register', false);
    }

    public function test_module_providers_are_not_registered(): void
    {
        $this->assertSame([], $this->app->getProviders(ModuleServiceProvider::class));
        $this->assertCount(4, $this->app->make(ModuleRegistry::class)->all());
    }
}
