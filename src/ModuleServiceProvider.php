<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries;

use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Base service provider of a module.
 *
 * Loads what the module ships, by convention, relative to the module root
 * (the parent of the directory this provider lives in, e.g. modules/Billing):
 *
 * - config/*.php: config/billing.php is merged as `billing`, any other file
 *   (config/gateways.php) as `billing.gateways`;
 * - routes/web.php with the `web` middleware group, routes/api.php with the
 *   `api` group and the `api` prefix;
 * - database/migrations;
 * - lang: `__('billing::messages.paid')` and JSON translations.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Modules this module uses, by name (e.g. ['Catalog']).
     *
     * @var list<string>
     */
    protected array $dependsOn = [];

    /**
     * URI prefix of routes/api.php.
     */
    protected string $apiPrefix = 'api';

    private ?string $modulePath = null;

    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot(): void
    {
        $this->bootRoutes();
        $this->bootMigrations();
        $this->bootTranslations();
    }

    /**
     * @return list<string>
     */
    public function dependsOn(): array
    {
        return array_values(array_unique($this->dependsOn));
    }

    /**
     * Module name: the name of the module directory (Billing).
     */
    public function moduleName(): string
    {
        return basename($this->modulePath());
    }

    /**
     * Key of the module config and namespace of its translations (billing).
     */
    public function moduleAlias(): string
    {
        return Str::snake($this->moduleName());
    }

    /**
     * Module root directory, or a path inside it. By default the parent of
     * the directory this provider lives in (modules/Billing for
     * modules/Billing/Providers/BillingServiceProvider.php).
     */
    public function modulePath(string $path = ''): string
    {
        $this->modulePath ??= dirname((string) (new ReflectionClass($this))->getFileName(), 2);

        return $path === '' ? $this->modulePath : $this->modulePath.DIRECTORY_SEPARATOR.$path;
    }

    protected function registerConfig(): void
    {
        $files = glob($this->modulePath('config').DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file, '.php');

            $this->mergeConfigFrom($file, $name === $this->moduleAlias() ? $name : $this->moduleAlias().'.'.$name);
        }
    }

    protected function bootRoutes(): void
    {
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        if (is_file($web = $this->modulePath('routes'.DIRECTORY_SEPARATOR.'web.php'))) {
            Route::middleware('web')->group($web);
        }

        if (is_file($api = $this->modulePath('routes'.DIRECTORY_SEPARATOR.'api.php'))) {
            Route::middleware('api')->prefix($this->apiPrefix)->group($api);
        }
    }

    protected function bootMigrations(): void
    {
        if (is_dir($migrations = $this->modulePath('database'.DIRECTORY_SEPARATOR.'migrations'))) {
            $this->loadMigrationsFrom($migrations);
        }
    }

    protected function bootTranslations(): void
    {
        if (is_dir($lang = $this->modulePath('lang'))) {
            $this->loadTranslationsFrom($lang, $this->moduleAlias());
            $this->loadJsonTranslationsFrom($lang);
        }
    }
}
