<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Toshkq93\Boundaries\ModuleServiceProvider;
use Toshkq93\Boundaries\Tests\Fixtures\Modules\Billing\Providers\BillingServiceProvider;
use Toshkq93\Boundaries\Tests\Fixtures\Modules\Catalog\Providers\CatalogServiceProvider;
use Toshkq93\Boundaries\Tests\Fixtures\Modules\Shipping\Providers\ShippingServiceProvider;
use Toshkq93\Boundaries\Tests\TestCase;

class ModuleServiceProviderTest extends TestCase
{
    public function test_module_providers_are_registered_after_their_dependencies(): void
    {
        $registered = array_map(get_class(...), array_values($this->app->getProviders(ModuleServiceProvider::class)));

        // Billing depends on Catalog, Shipping on Billing (and on the missing Warehouse); Legacy has no provider.
        $this->assertSame([
            CatalogServiceProvider::class,
            BillingServiceProvider::class,
            ShippingServiceProvider::class,
        ], $registered);
    }

    public function test_module_name_path_and_alias(): void
    {
        $provider = $this->app->getProvider(CatalogServiceProvider::class);

        $this->assertInstanceOf(ModuleServiceProvider::class, $provider);
        $this->assertSame('Catalog', $provider->moduleName());
        $this->assertSame('catalog', $provider->moduleAlias());
        $this->assertSame(realpath(__DIR__.'/../Fixtures/Modules/Catalog'), $provider->modulePath());
        $this->assertSame([], $provider->dependsOn());
    }

    public function test_config_files_are_merged_under_the_module_alias(): void
    {
        $this->assertSame('EUR', config('billing.currency'));
        $this->assertSame('stripe', config('billing.gateways.default'));
    }

    public function test_web_routes_are_loaded_with_the_web_group(): void
    {
        $this->get('/catalog')->assertOk()->assertSee('catalog');

        $route = $this->app['router']->getRoutes()->getByName('catalog.index');
        $this->assertNotNull($route);
        $this->assertContains('web', $route->gatherMiddleware());
    }

    public function test_api_routes_are_loaded_with_the_api_group_and_prefix(): void
    {
        $this->getJson('/api/catalog/products')->assertOk()->assertExactJson(['data' => []]);

        $route = $this->app['router']->getRoutes()->getByName('api.catalog.products');
        $this->assertNotNull($route);
        $this->assertContains('api', $route->gatherMiddleware());
    }

    public function test_migrations_are_loaded(): void
    {
        $this->artisan('migrate')->assertSuccessful();

        $this->assertTrue(Schema::hasTable('catalog_products'));
    }

    public function test_translations_are_loaded(): void
    {
        $this->assertSame('Product catalog', __('catalog::messages.title'));
        $this->assertSame('Add to cart (catalog)', __('Add to cart'));
    }
}
