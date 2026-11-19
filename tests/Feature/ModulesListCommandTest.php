<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Toshkq93\Boundaries\Modules\ModuleRegistry;
use Toshkq93\Boundaries\Tests\TestCase;

class ModulesListCommandTest extends TestCase
{
    public function test_lists_modules_with_providers_and_dependencies(): void
    {
        Artisan::call('modules:list');
        $output = Artisan::output();

        foreach (['Billing', 'Catalog', 'Legacy', 'Shipping'] as $module) {
            $this->assertStringContainsString($module, $output);
        }

        $this->assertStringContainsString('BillingServiceProvider', $output);
        $this->assertStringContainsString('not found (LegacyServiceProvider)', $output);
        $this->assertStringContainsString('Warehouse (missing)', $output);
        $this->assertMatchesRegularExpression('/Billing\s.*Catalog/', $output);
    }

    public function test_lists_modules_as_json(): void
    {
        Artisan::call('modules:list', ['--json' => true]);

        /** @var list<array<string, mixed>> $modules */
        $modules = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['Billing', 'Catalog', 'Legacy', 'Shipping'], array_column($modules, 'name'));
        $this->assertSame([
            'name' => 'Shipping',
            'namespace' => self::FIXTURES_NAMESPACE.'\\Shipping',
            'path' => realpath(__DIR__.'/../Fixtures/Modules/Shipping'),
            'provider' => self::FIXTURES_NAMESPACE.'\\Shipping\\Providers\\ShippingServiceProvider',
            'depends_on' => ['Billing', 'Warehouse'],
            'missing_dependencies' => ['Warehouse'],
        ], $modules[3]);
        $this->assertNull($modules[2]['provider']);
    }

    public function test_says_when_there_are_no_modules(): void
    {
        config()->set('boundaries.path', __DIR__.'/../Fixtures/nothing-here');
        $this->app->make(ModuleRegistry::class)->flush();

        $this->artisan('modules:list')
            ->expectsOutputToContain('No modules in')
            ->assertSuccessful();
    }
}
