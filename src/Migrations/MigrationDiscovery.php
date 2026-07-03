<?php

declare(strict_types=1);

namespace SchemaGuard\Migrations;

use Illuminate\Filesystem\Filesystem;
use SchemaGuard\Exceptions\ConfigurationException;
use SchemaGuard\Pipeline\AnalysisRequest;
use SchemaGuard\Pipeline\MigrationSource;
use SchemaGuard\Policy\PolicyConfiguration;
use SplFileInfo;

final class MigrationDiscovery
{
    /**
     * @param array<string, mixed>|PolicyConfiguration $config
     */
    public function __construct(
        private readonly Filesystem $files,
        private readonly array|PolicyConfiguration $config = [],
        private readonly ?GitCommandRunner $git = null,
    ) {
    }

    /**
     * @param array<string, mixed>|AnalysisRequest $options
     *
     * @return string[]
     */
    public function resolve(array|AnalysisRequest $options = []): array
    {
        if ($options instanceof AnalysisRequest) {
            return match ($options->migrationSource) {
                MigrationSource::EXPLICIT => $this->sortPaths($this->explicit($options->explicitMigrations)),
                MigrationSource::GIT_DIFF => $this->sortPaths($this->gitDiff($options->gitBase, $this->migrationPaths())),
                MigrationSource::PENDING => $this->sortPaths($this->pending($this->migrationPaths())),
            };
        }

        $strategy = strtolower((string) ($options['strategy'] ?? $options['source'] ?? ''));

        if ($strategy === 'git_diff' || $strategy === 'diff' || ($options['diff'] ?? false) === true) {
            return $this->sortPaths($this->gitDiff((string) ($options['base'] ?? 'HEAD'), $this->migrationPaths()));
        }

        $explicit = $options['migrations'] ?? $options['paths'] ?? [];
        if (is_string($explicit)) {
            $explicit = [$explicit];
        }

        if (is_array($explicit) && $explicit !== []) {
            return $this->sortPaths($this->explicit($explicit));
        }

        $migrationPaths = $options['migration_paths']
            ?? $this->migrationPaths();

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
                throw new ConfigurationException('Migration paths must be strings.');
            }

            $absolute = $this->absolutePath($path);

            if (! $this->files->exists($absolute)) {
                throw new ConfigurationException("Migration file does not exist: {$path}");
            }

            if (strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) !== 'php') {
                throw new ConfigurationException("Migration file must be a PHP file: {$path}");
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

    /**
     * @param string[] $migrationPaths
     *
     * @return string[]
     */
    private function gitDiff(string $base, array $migrationPaths): array
    {
        $base = trim($base);
        if ($base === '') {
            throw new ConfigurationException('Git base ref cannot be empty.');
        }

        $pathspecs = array_values(array_filter(
            array_map(static fn (string $path): string => str_replace('\\', '/', $path), $migrationPaths),
            static fn (string $path): bool => $path !== '',
        ));

        $command = [
            'git',
            'diff',
            '--name-only',
            '--diff-filter=AM',
            $base,
            '--',
            ...$pathspecs,
        ];

        $cwd = getcwd() ?: base_path();
        $runner = $this->git ?? new NativeGitCommandRunner();
        $result = $runner->run($command, $cwd);

        if ($result->exitCode !== 0) {
            $message = trim($result->error) ?: trim($result->output) ?: 'Git diff migration discovery failed.';
            throw new ConfigurationException($message);
        }

        $paths = [];
        foreach (preg_split('/\R/', trim($result->output)) ?: [] as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $absolute = $this->absolutePath(trim($path));
            if ($this->files->exists($absolute) && strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) === 'php') {
                $paths[] = $absolute;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return string[]
     */
    private function migrationPaths(): array
    {
        if ($this->config instanceof PolicyConfiguration) {
            return $this->config->migrationPaths();
        }

        $paths = $this->config['migration_paths'] ?? config('schemaguard.migration_paths', ['database/migrations']);

        if (is_string($paths)) {
            return [$paths];
        }

        if (! is_array($paths)) {
            return [];
        }

        return array_values(array_filter($paths, static fn (mixed $path): bool => is_string($path)));
    }

    private function absolutePath(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (! $this->isAbsolutePath($normalized)) {
            $cwdRelative = (getcwd() ?: '') . DIRECTORY_SEPARATOR . $normalized;
            $normalized = $this->files->exists($cwdRelative) ? $cwdRelative : base_path($normalized);
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
