<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use stdClass;

/**
 * Adds a PSR-4 entry to composer.json unless the namespace already loads
 * from the directory.
 */
class ComposerAutoload
{
    public function __construct(private readonly Filesystem $files) {}

    public function ensurePsr4(string $composerFile, string $namespace, string $directory): AutoloadStatus
    {
        if (! $this->files->isFile($composerFile)) {
            return AutoloadStatus::NoComposerJson;
        }

        $base = self::normalize(dirname($composerFile));
        $directory = self::normalize($directory);

        if (! str_starts_with($directory.'/', $base.'/') || $directory === $base) {
            return AutoloadStatus::OutsideProject;
        }

        $json = json_decode($this->files->get($composerFile));

        if (! $json instanceof stdClass) {
            throw new RuntimeException("Could not parse {$composerFile}.");
        }

        $prefix = trim($namespace, '\\').'\\';
        $autoload = $json->autoload ?? new stdClass;

        if (! $autoload instanceof stdClass) {
            throw new RuntimeException("Unexpected \"autoload\" section in {$composerFile}.");
        }

        $psr4 = $autoload->{'psr-4'} ?? new stdClass;

        if (! $psr4 instanceof stdClass) {
            throw new RuntimeException("Unexpected \"autoload.psr-4\" section in {$composerFile}.");
        }

        /** @var array<string, string|list<string>> $mappings */
        $mappings = get_object_vars($psr4);

        foreach ($mappings as $mappedPrefix => $paths) {
            if (! str_starts_with($prefix, $mappedPrefix)) {
                continue;
            }

            $rest = str_replace('\\', '/', substr($prefix, strlen($mappedPrefix)));

            foreach ((array) $paths as $path) {
                if (self::normalize($base.'/'.$path.'/'.$rest) === $directory) {
                    return AutoloadStatus::AlreadyLoaded;
                }
            }
        }

        if (isset($mappings[$prefix])) {
            return AutoloadStatus::Conflict;
        }

        $original = $this->files->get($composerFile);
        $path = substr($directory, strlen($base) + 1).'/';

        $psr4->{$prefix} = $path;
        $autoload->{'psr-4'} = $psr4;
        $json->autoload = $autoload;

        $updated = $this->insertEntry($original, $prefix, $path);

        // Keep the formatting of the file; rewrite it only when the entry cannot be inserted in place.
        if ($updated === null || json_decode($updated) != $json) {
            $updated = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
        }

        $this->files->put($composerFile, $updated);

        return AutoloadStatus::Added;
    }

    /**
     * Adds `"Prefix\\": "path/"` as the last entry of the existing
     * "autoload"."psr-4" object, leaving the rest of the text untouched.
     */
    private function insertEntry(string $json, string $prefix, string $path): ?string
    {
        if (preg_match('/"autoload"\s*:\s*\{/', $json, $autoload, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $autoloadEnd = self::closingBrace($json, $autoload[0][1] + strlen($autoload[0][0]) - 1);

        if ($autoloadEnd === null
            || preg_match('/"psr-4"\s*:\s*\{/', $json, $psr4, PREG_OFFSET_CAPTURE, $autoload[0][1]) !== 1
            || $psr4[0][1] > $autoloadEnd) {
            return null;
        }

        $open = $psr4[0][1] + strlen($psr4[0][0]) - 1;
        $close = self::closingBrace($json, $open);

        if ($close === null) {
            return null;
        }

        $entry = json_encode($prefix, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).': '
            .json_encode($path, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Indentation of the line with "psr-4", and of its entries.
        $lineStart = (int) strrpos(substr($json, 0, $psr4[0][1]), "\n") + 1;
        $outer = (string) preg_replace('/\S.*$/s', '', substr($json, $lineStart, $psr4[0][1] - $lineStart));
        $inner = $outer.(str_contains($outer, "\t") ? "\t" : '    ');
        $body = substr($json, $open + 1, $close - $open - 1);

        if (trim($body) === '') {
            return substr($json, 0, $open + 1)."\n{$inner}{$entry}\n{$outer}".substr($json, $close);
        }

        if (preg_match('/^[ \t]+(?=")/m', $body, $indent) === 1) {
            $inner = $indent[0];
        }

        $last = $open + 1 + strlen(rtrim($body));

        return substr($json, 0, $last).(str_contains($body, "\n") ? ",\n{$inner}" : ', ').$entry.substr($json, $last);
    }

    /**
     * Position of the brace that closes the one at $open (JSON strings skipped).
     */
    private static function closingBrace(string $json, int $open): ?int
    {
        $depth = 0;
        $length = strlen($json);

        for ($i = $open; $i < $length; $i++) {
            $char = $json[$i];

            if ($char === '"') {
                for ($i++; $i < $length && $json[$i] !== '"'; $i++) {
                    if ($json[$i] === '\\') {
                        $i++;
                    }
                }
            } elseif ($char === '{' || $char === '[') {
                $depth++;
            } elseif ($char === '}' || $char === ']') {
                if (--$depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = [];

        foreach (explode('/', $path) as $i => $part) {
            if ($part === '.' || ($part === '' && $i > 0)) {
                continue;
            }

            if ($part === '..' && $parts !== [] && end($parts) !== '') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}
