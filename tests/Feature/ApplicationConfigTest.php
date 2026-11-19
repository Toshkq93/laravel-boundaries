<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Tests\Feature;

use Toshkq93\Boundaries\Tests\TestCase;

class ApplicationConfigTest extends TestCase
{
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        // As if config/billing.php of the application set it.
        $app['config']->set('billing', ['currency' => 'USD']);
    }

    public function test_application_config_wins_over_module_config(): void
    {
        $this->assertSame('USD', config('billing.currency'));
        $this->assertSame('stripe', config('billing.gateways.default'));
    }
}
