<?php

declare(strict_types=1);

namespace SchemaGuard\Migrations;

use BadMethodCallException;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use SplFileInfo;

final class MigrationDiscovery
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly Filesystem $files,
        private readonly array $config = [],
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return string[]
     */
    public function resolve(array $options = []): array
    {
        $strategy = strtolower((string) ($options['strategy'] ?? $options['source'] ?? ''));

        if ($strategy === 'git_diff' || $strategy === 'diff' || ($options['diff'] ?? false) === true) {
            throw new BadMethodCallException('Git diff migration discovery is not supported until Phase 5.');
        }

        $explicit = $options['migrations'] ?? $options['paths'] ?? [];
        if (is_string($explicit)) {
            $explicit = [$explicit];
        }

        if (is_array($explicit) && $explicit !== []) {
            return $this->sortPaths($this->explicit($explicit));
        }

        $migrationPaths = $options['migration_paths']
            ?? $this->config['migration_paths']
            ?? config('schemaguard.migration_paths', ['database/migrations']);

        if (is_string($migrationPaths)) {
            $migrationPaths = [$migrationPaths];
        }

        return $this->sortPaths($this->pending($migrationPaths));
    }

    /**
     * @param array<int, mixed> $paths
     *
     * @return string[]
     */
    private function explicit(array $paths): array
    {
        $resolved = [];

        foreach ($paths as $path) {
            if (! is_string($path)) {
                throw new InvalidArgumentException('Migration paths must be strings.');
            }

            $absolute = $this->absolutePath($path);

            if (! $this->files->exists($absolute)) {
                throw new InvalidArgumentException("Migration file does not exist: {$path}");
            }

            if (strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) !== 'php') {
                throw new InvalidArgumentException("Migration file must be a PHP file: {$path}");
            }

            $resolved[] = $absolute;
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param array<int, mixed> $migrationPaths
     *
     * @return string[]
     */
    private function pending(array $migrationPaths): array
    {
        $files = [];

        foreach ($migrationPaths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $directory = $this->absolutePath($path);
            if (! $this->files->isDirectory($directory)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach ($this->files->allFiles($directory) as $file) {
                if (strtolower($file->getExtension()) === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return array_values(array_unique($files));
    }

    private function absolutePath(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (! $this->isAbsolutePath($normalized)) {
            $normalized = base_path($normalized);
        }

        return realpath($normalized) ?: $normalized;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || (bool) preg_match('/^[A-Za-z]:\\\\/', $path);
    }

    /**
     * @param string[] $paths
     *
     * @return string[]
     */
    private function sortPaths(array $paths): array
    {
        usort($paths, static function (string $left, string $right): int {
            return [basename($left), $left] <=> [basename($right), $right];
        });

        return $paths;
    }
}
