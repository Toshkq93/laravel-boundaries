<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Toshkq93\Boundaries\Support\AutoloadStatus;
use Toshkq93\Boundaries\Support\ComposerAutoload;

class ComposerAutoloadTest extends TestCase
{
    private string $dir;

    private Filesystem $files;

    protected function setUp(): void
    {
        $this->files = new Filesystem;
        $this->dir = sys_get_temp_dir().'/boundaries-'.bin2hex(random_bytes(4));
        $this->files->ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->dir);
    }

    public function test_adds_the_entry_and_keeps_the_rest_of_the_file(): void
    {
        $this->composer([
            'name' => 'acme/app',
            'require' => new \stdClass,
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
            'extra' => ['laravel' => ['dont-discover' => []]],
        ]);

        $status = $this->autoload()->ensurePsr4($this->dir.'/composer.json', 'Modules', $this->dir.'/modules');

        $this->assertSame(AutoloadStatus::Added, $status);
        $json = $this->files->get($this->dir.'/composer.json');
        $this->assertStringContainsString('"require": {}', $json);
        $this->assertStringContainsString("\"App\\\\\": \"app/\",\n            \"Modules\\\\\": \"modules/\"", $json);
        $this->assertStringContainsString('"dont-discover": []', $json);
    }

    public function test_keeps_the_formatting_of_the_file(): void
    {
        $this->files->put($this->dir.'/composer.json', <<<'JSON'
            {
              "name": "acme/app",
              "keywords": ["laravel", "framework"],
              "autoload": {
                "files": ["helpers.php"],
                "psr-4": {
                  "App\\": "app/"
                }
              },
              "autoload-dev": {
                "psr-4": {"Tests\\": "tests/"}
              }
            }

            JSON);

        $this->assertSame(AutoloadStatus::Added, $this->autoload()->ensurePsr4($this->dir.'/composer.json', 'Modules', $this->dir.'/modules'));
        $this->assertSame(<<<'JSON'
            {
              "name": "acme/app",
              "keywords": ["laravel", "framework"],
              "autoload": {
                "files": ["helpers.php"],
                "psr-4": {
                  "App\\": "app/",
                  "Modules\\": "modules/"
                }
              },
              "autoload-dev": {
                "psr-4": {"Tests\\": "tests/"}
              }
            }

            JSON, $this->files->get($this->dir.'/composer.json'));
    }

    public function test_fills_an_empty_psr4_object_in_place(): void
    {
        $this->files->put($this->dir.'/composer.json', <<<'JSON'
            {
                "keywords": ["a", "b"],
                "autoload": {
                    "psr-4": {}
                }
            }
            JSON);

        $this->autoload()->ensurePsr4($this->dir.'/composer.json', 'Modules', $this->dir.'/modules');

        $this->assertSame(<<<'JSON'
            {
                "keywords": ["a", "b"],
                "autoload": {
                    "psr-4": {
                        "Modules\\": "modules/"
                    }
                }
            }
            JSON, $this->files->get($this->dir.'/composer.json'));
    }

    public function test_adds_the_autoload_section_when_missing(): void
    {
        $this->composer(['name' => 'acme/app']);

        $this->assertSame(AutoloadStatus::Added, $this->autoload()->ensurePsr4($this->dir.'/composer.json', 'Modules', $this->dir.'/src/modules'));
        $this->assertSame(['Modules\\' => 'src/modules/'], json_decode($this->files->get($this->dir.'/composer.json'), true)['autoload']['psr-4']);
    }

    public function test_detects_an_entry_that_already_loads_the_namespace(): void
    {
        $this->composer(['autoload' => ['psr-4' => ['App\\' => ['app/'], 'Modules\\' => './modules']]]);

        $this->assertSame(AutoloadStatus::AlreadyLoaded, $this->autoload()->ensurePsr4($this->dir.'/composer.json', 'Modules', $this->dir.'/modules'));
        // A parent namespace mapping covers it too: App\Modules from app/Modules.
        $this->assertSame(AutoloadStatus::AlreadyLoaded, $this->autoload()->ensurePsr4($this->dir.'/composer.json', 'App\\Modules', $this->dir.'/app/Modules'));
    }

    public function test_reports_a_conflicting_entry(): void
    {
        $this->composer(['autoload' => ['psr-4' => ['Modules\\' => 'src/']]]);

        $this->assertSame(AutoloadStatus::Conflict, $this->autoload()->ensurePsr4($this->dir.'/composer.json', 'Modules', $this->dir.'/modules'));
    }

    public function test_reports_a_directory_outside_the_project_and_a_missing_file(): void
    {
        $this->composer([]);

        $this->assertSame(AutoloadStatus::OutsideProject, $this->autoload()->ensurePsr4($this->dir.'/composer.json', 'Modules', '/elsewhere/modules'));
        $this->assertSame(AutoloadStatus::NoComposerJson, $this->autoload()->ensurePsr4($this->dir.'/missing.json', 'Modules', $this->dir.'/modules'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function composer(array $data): void
    {
        $this->files->put($this->dir.'/composer.json', json_encode($data === [] ? new \stdClass : $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function autoload(): ComposerAutoload
    {
        return new ComposerAutoload($this->files);
    }
}
