<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use Toshkq93\Boundaries\Console\Concerns\ShowsRelativePaths;
use Toshkq93\Boundaries\Modules\Module;
use Toshkq93\Boundaries\Modules\ModuleRegistry;
use Toshkq93\Boundaries\Support\AutoloadStatus;
use Toshkq93\Boundaries\Support\ComposerAutoload;

#[AsCommand(name: 'make:module')]
class MakeModuleCommand extends Command
{
    use ShowsRelativePaths;

    protected $signature = 'make:module
        {name : Module name, e.g. Billing}
        {--force : Overwrite the files of an existing module}';

    protected $description = 'Create a module: service provider, routes, config, migrations, translations and public API folders';

    public function handle(Filesystem $files, ModuleRegistry $modules, ComposerAutoload $autoload): int
    {
        $argument = $this->argument('name');
        $name = Str::studly(is_string($argument) ? $argument : '');

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            $this->components->error("[{$name}] is not a valid module name: use letters and digits, e.g. Billing.");

            return self::FAILURE;
        }

        $path = $modules->path($name);

        if ($files->isDirectory($path) && ! $this->option('force')) {
            $this->components->error("Module [{$name}] already exists in {$this->relative($path)}. Use --force to overwrite its files.");

            return self::FAILURE;
        }

        $namespace = $modules->namespace($name);
        $replace = [
            '{{ name }}' => $name,
            '{{ namespace }}' => $namespace,
            '{{ alias }}' => Str::snake($name),
            '{{ slug }}' => Str::kebab($name),
        ];

        $created = [];

        foreach ($this->stubs($name) as $target => $stub) {
            $file = $path.DIRECTORY_SEPARATOR.$target;
            $files->ensureDirectoryExists(dirname($file));
            $files->put($file, strtr($files->get($this->stubPath($stub)), $replace));
            $created[] = $file;
        }

        foreach ($this->keptDirectories() as $directory) {
            $keep = $path.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.'.gitkeep';

            if ($files->isDirectory(dirname($keep)) && ! $files->isEmptyDirectory(dirname($keep))) {
                continue;
            }

            $files->ensureDirectoryExists(dirname($keep));
            $files->put($keep, '');
            $created[] = dirname($keep);
        }

        $modules->flush();

        $this->components->info("Module [{$name}] created in {$this->relative($path)}.");

        foreach ($created as $file) {
            $this->components->twoColumnDetail($this->relative($file), '<fg=green;options=bold>CREATED</>');
        }

        $this->ensureAutoload($autoload, $modules, Module::providerClassFor($namespace, $name));

        return self::SUCCESS;
    }

    /**
     * Files of a new module and their stubs.
     *
     * @return array<string, string>
     */
    protected function stubs(string $name): array
    {
        return [
            'Providers/'.$name.'ServiceProvider.php' => 'provider.stub',
            'routes/web.php' => 'routes-web.stub',
            'routes/api.php' => 'routes-api.stub',
            'config/'.Str::snake($name).'.php' => 'config.stub',
        ];
    }

    /**
     * Empty directories created with a .gitkeep.
     *
     * @return list<string>
     */
    protected function keptDirectories(): array
    {
        $public = $this->laravel->make(Config::class)->get('boundaries.public', []);

        return [
            ...array_values(array_filter(is_array($public) ? $public : [], is_string(...))),
            'database/migrations',
            'lang',
        ];
    }

    protected function stubPath(string $stub): string
    {
        $published = $this->laravel->basePath('stubs/boundaries/module/'.$stub);

        return is_file($published) ? $published : __DIR__.'/../../stubs/module/'.$stub;
    }

    private function ensureAutoload(ComposerAutoload $autoload, ModuleRegistry $modules, string $provider): void
    {
        $composerFile = $this->laravel->basePath('composer.json');
        $prefix = $modules->namespace().'\\';
        $path = $this->relative($modules->path()).'/';

        $status = $autoload->ensurePsr4($composerFile, $modules->namespace(), $modules->path());

        match ($status) {
            AutoloadStatus::Added => $this->dumpAutoload($prefix, $path),
            AutoloadStatus::AlreadyLoaded => null,
            AutoloadStatus::Conflict => $this->components->warn(
                "composer.json maps \"{$prefix}\" to another directory. Map it to \"{$path}\" so that {$provider} can be loaded.",
            ),
            AutoloadStatus::OutsideProject, AutoloadStatus::NoComposerJson => $this->components->warn(
                "Add a PSR-4 entry for \"{$prefix}\" ({$modules->path()}) to your autoloader so that {$provider} can be loaded.",
            ),
        };
    }

    private function dumpAutoload(string $prefix, string $path): void
    {
        $this->components->info("Added \"{$prefix}\": \"{$path}\" to the PSR-4 autoload of composer.json.");

        try {
            $this->components->task('Running composer dump-autoload', function (): void {
                /** @var Composer $composer */
                $composer = $this->laravel->make('composer');
                $composer->dumpAutoloads();
            });
        } catch (Throwable) {
            $this->components->warn('Run "composer dump-autoload" to load the new module.');
        }
    }
}
