<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Toshkq93\Boundaries\Console\Concerns\ShowsRelativePaths;
use Toshkq93\Boundaries\Modules\Module;
use Toshkq93\Boundaries\Modules\ModuleRegistry;

#[AsCommand(name: 'modules:list')]
class ModulesListCommand extends Command
{
    use ShowsRelativePaths;

    protected $signature = 'modules:list {--json : Output the modules as JSON}';

    protected $description = 'List the modules, their service providers and dependencies';

    public function handle(ModuleRegistry $modules): int
    {
        $all = $modules->all();

        if ($this->option('json')) {
            $this->line((string) json_encode(
                array_values(array_map(fn (Module $module): array => [
                    'name' => $module->name,
                    'namespace' => $module->namespace,
                    'path' => $this->relative($module->path),
                    'provider' => $module->provider !== null ? $module->providerClass() : null,
                    'depends_on' => $module->dependsOn(),
                    'missing_dependencies' => $this->missing($module, $all),
                ], $all)),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return self::SUCCESS;
        }

        if ($all === []) {
            $this->components->info("No modules in {$this->relative($modules->path())}. Create one with: php artisan make:module Billing");

            return self::SUCCESS;
        }

        $this->table(
            ['Module', 'Namespace', 'Path', 'Provider', 'Depends on'],
            array_map(fn (Module $module): array => [
                $module->name,
                $module->namespace,
                $this->relative($module->path),
                $module->provider !== null
                    ? '<fg=green>'.class_basename($module->providerClass()).'</>'
                    : '<fg=yellow>not found ('.class_basename($module->providerClass()).')</>',
                $this->dependencies($module, $all),
            ], array_values($all)),
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, Module>  $all
     */
    private function dependencies(Module $module, array $all): string
    {
        $missing = $this->missing($module, $all);

        $names = array_map(
            fn (string $name): string => in_array($name, $missing, true) ? "<fg=red>{$name} (missing)</>" : $name,
            $module->dependsOn(),
        );

        return $names === [] ? '—' : implode(', ', $names);
    }

    /**
     * @param  array<string, Module>  $all
     * @return list<string>
     */
    private function missing(Module $module, array $all): array
    {
        return array_values(array_filter($module->dependsOn(), fn (string $name): bool => ! isset($all[$name])));
    }
}
