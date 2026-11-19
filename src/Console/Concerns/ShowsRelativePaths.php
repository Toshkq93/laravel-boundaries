<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Console\Concerns;

trait ShowsRelativePaths
{
    /**
     * Path relative to the application root, for output.
     */
    protected function relative(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', $this->laravel->basePath()), '/').'/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
