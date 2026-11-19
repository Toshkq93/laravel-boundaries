<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Mockery\MockInterface;
use Toshkq93\Boundaries\Modules\ModuleRegistry;
use Toshkq93\Boundaries\ModuleServiceProvider;
use Toshkq93\Boundaries\Tests\TestCase;

class MakeModuleCommandTest extends TestCase
{
    private Filesystem $files;

    private string $composerBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->composerBackup = $this->files->get(base_path('composer.json'));
        $this->files->deleteDirectory(base_path('modules-test'));
        $this->files->deleteDirectory(base_path('stubs/boundaries'));

        config()->set('boundaries.path', 'modules-test');
        config()->set('boundaries.namespace', 'ModulesTest');
    }

    protected function tearDown(): void
    {
        $this->files->put(base_path('composer.json'), $this->composerBackup);
        $this->files->deleteDirectory(base_path('modules-test'));
        $this->files->deleteDirectory(base_path('stubs/boundaries'));

        parent::tearDown();
    }

    public function test_creates_a_module(): void
    {
        $this->expectComposerDump(1);

        $this->artisan('make:module', ['name' => 'billing'])
            ->expectsOutputToContain('Module [Billing] created in modules-test/Billing.')
            ->assertSuccessful();

        $module = base_path('modules-test/Billing');

        foreach ([
            'Providers/BillingServiceProvider.php',
            'routes/web.php',
            'routes/api.php',
            'config/billing.php',
            'Contracts/.gitkeep',
            'Events/.gitkeep',
            'Data/.gitkeep',
            'database/migrations/.gitkeep',
            'lang/.gitkeep',
        ] as $file) {
            $this->assertFileExists($module.'/'.$file);
        }

        $provider = $this->files->get($module.'/Providers/BillingServiceProvider.php');
        $this->assertStringContainsString('namespace ModulesTest\Billing\Providers;', $provider);
        $this->assertStringContainsString('class BillingServiceProvider extends ModuleServiceProvider', $provider);
        $this->assertStringContainsString('protected array $dependsOn = [];', $provider);
        $this->assertStringContainsString("config('billing.key')", $this->files->get($module.'/config/billing.php'));
    }

    public function test_adds_the_psr4_entry_to_composer_json_once(): void
    {
        $this->expectComposerDump(1);

        $this->artisan('make:module', ['name' => 'Billing'])
            ->expectsOutputToContain('Added "ModulesTest\": "modules-test/" to the PSR-4 autoload of composer.json.')
            ->assertSuccessful();
        $this->artisan('make:module', ['name' => 'Catalog'])
            ->doesntExpectOutputToContain('Added')
            ->assertSuccessful();

        $composer = json_decode($this->files->get(base_path('composer.json')), true);

        $this->assertSame('modules-test/', $composer['autoload']['psr-4']['ModulesTest\\']);
        $this->assertStringEndsWith("}\n", $this->files->get(base_path('composer.json')));
    }

    public function test_generated_provider_is_a_module_provider(): void
    {
        $this->expectComposerDump(1);

        $this->artisan('make:module', ['name' => 'Invoicing'])->assertSuccessful();

        require base_path('modules-test/Invoicing/Providers/InvoicingServiceProvider.php');

        $registry = $this->app->make(ModuleRegistry::class);
        $module = $registry->find('Invoicing');

        $this->assertNotNull($module);
        $this->assertInstanceOf(ModuleServiceProvider::class, $module->provider);
        $this->assertSame('Invoicing', $module->provider->moduleName());
        $this->assertSame(realpath(base_path('modules-test/Invoicing')), realpath($module->provider->modulePath()));
    }

    public function test_refuses_to_overwrite_an_existing_module(): void
    {
        $this->expectComposerDump(1);

        $this->artisan('make:module', ['name' => 'Billing'])->assertSuccessful();
        $this->files->put(base_path('modules-test/Billing/routes/web.php'), '<?php // mine');

        $this->artisan('make:module', ['name' => 'Billing'])
            ->expectsOutputToContain('Module [Billing] already exists')
            ->assertFailed();
        $this->assertSame('<?php // mine', $this->files->get(base_path('modules-test/Billing/routes/web.php')));

        $this->artisan('make:module', ['name' => 'Billing', '--force' => true])->assertSuccessful();
        $this->assertStringContainsString('Web routes of the Billing module', $this->files->get(base_path('modules-test/Billing/routes/web.php')));
    }

    public function test_rejects_an_invalid_name(): void
    {
        $this->expectComposerDump(0);

        $this->artisan('make:module', ['name' => '9lives'])
            ->expectsOutputToContain('is not a valid module name')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist(base_path('modules-test'));
    }

    public function test_uses_published_stubs(): void
    {
        $this->expectComposerDump(1);
        $this->files->ensureDirectoryExists(base_path('stubs/boundaries/module'));
        $this->files->put(base_path('stubs/boundaries/module/routes-api.stub'), "<?php\n\n// custom {{ name }} {{ slug }}\n");

        $this->artisan('make:module', ['name' => 'OrderHistory'])->assertSuccessful();

        $this->assertSame("<?php\n\n// custom OrderHistory order-history\n", $this->files->get(base_path('modules-test/OrderHistory/routes/api.php')));
        $this->assertFileExists(base_path('modules-test/OrderHistory/config/order_history.php'));
    }

    public function test_warns_when_composer_maps_the_namespace_elsewhere(): void
    {
        $this->expectComposerDump(0);

        $composer = json_decode($this->composerBackup, true);
        $composer['autoload']['psr-4']['ModulesTest\\'] = 'src/Modules/';
        $this->files->put(base_path('composer.json'), json_encode($composer));

        $this->artisan('make:module', ['name' => 'Billing'])
            ->expectsOutputToContain('maps "ModulesTest\" to another directory')
            ->assertSuccessful();
    }

    private function expectComposerDump(int $times): void
    {
        $this->mock('composer', function (MockInterface $mock) use ($times): void {
            $mock->shouldReceive('dumpAutoloads')->times($times);
        });
    }
}
