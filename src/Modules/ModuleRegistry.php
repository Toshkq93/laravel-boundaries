<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Modules;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Toshkq93\Boundaries\ModuleServiceProvider;

/**
 * Finds the modules in the configured directory: every subdirectory is a
 * module, and its provider is {namespace}\{Name}\Providers\{Name}ServiceProvider.
 */
class ModuleRegistry
{
    /** @var array<string, Module>|null */
    private ?array $modules = null;

    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
    ) {}

    /**
     * Absolute path of the modules directory, or of a module in it.
     */
    public function path(string $module = ''): string
    {
        $path = rtrim($this->configString('boundaries.path', 'modules'), '/\\');

        if (! self::isAbsolute($path)) {
            $path = $this->app->basePath($path);
        }

        return $module === '' ? $path : $path.DIRECTORY_SEPARATOR.$module;
    }

    /**
     * Root namespace of the modules (Modules), or the namespace of a module.
     */
    public function namespace(string $module = ''): string
    {
        $namespace = trim($this->configString('boundaries.namespace', 'Modules'), '\\');

        return $module === '' ? $namespace : $namespace.'\\'.$module;
    }

    /**
     * Modules sorted by name.
     *
     * @return array<string, Module>
     */
    public function all(): array
    {
        return $this->modules ??= $this->discover();
    }

    public function find(string $name): ?Module
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * Modules with every module after the modules it depends on; otherwise by
     * name. Dependencies on missing modules are ignored, and a cycle is
     * broken where it is found (modules:check reports both).
     *
     * @return list<Module>
     */
    public function inDependencyOrder(): array
    {
        $modules = $this->all();
        $state = [];
        $ordered = [];

        $visit = function (Module $module) use (&$visit, &$state, &$ordered, $modules): void {
            if (isset($state[$module->name])) {
                return;
            }

            $state[$module->name] = true;

            foreach ($module->dependsOn() as $dependency) {
                if (isset($modules[$dependency])) {
                    $visit($modules[$dependency]);
                }
            }

            $ordered[] = $module;
        };

        foreach ($modules as $module) {
            $visit($module);
        }

        return $ordered;
    }

    /**
     * Forget the modules found so far (after make:module, in tests).
     */
    public function flush(): void
    {
        $this->modules = null;
    }

    /**
     * @return array<string, Module>
     */
    protected function discover(): array
    {
        $modules = [];
        $directories = glob($this->path().DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory) {
            $name = basename($directory);

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
                continue;
            }

            $namespace = $this->namespace($name);

            $modules[$name] = new Module($name, $namespace, $directory, $this->provider(Module::providerClassFor($namespace, $name)));
        }

        ksort($modules);

        return $modules;
    }

    private function provider(string $class): ?ModuleServiceProvider
    {
        if (! class_exists($class) || ! is_subclass_of($class, ModuleServiceProvider::class)) {
            return null;
        }

        return new $class($this->app);
    }

    private function configString(string $key, string $default): string
    {
        $value = $this->config->get($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }
}
