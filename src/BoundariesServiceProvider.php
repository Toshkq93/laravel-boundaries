<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\ServiceProvider;
use Toshkq93\Boundaries\Console\MakeModuleCommand;
use Toshkq93\Boundaries\Console\ModulesListCommand;
use Toshkq93\Boundaries\Modules\ModuleRegistry;

class BoundariesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/boundaries.php', 'boundaries');

        $this->app->singleton(ModuleRegistry::class);

        if ($this->app->make(Config::class)->get('boundaries.auto_register', true)) {
            $this->registerModules();
        }
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            MakeModuleCommand::class,
            ModulesListCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../config/boundaries.php' => config_path('boundaries.php'),
        ], 'boundaries-config');

        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/boundaries'),
        ], 'boundaries-stubs');
    }

    private function registerModules(): void
    {
        foreach ($this->app->make(ModuleRegistry::class)->inDependencyOrder() as $module) {
            if ($module->provider !== null) {
                $this->app->register($module->provider);
            }
        }
    }
}
