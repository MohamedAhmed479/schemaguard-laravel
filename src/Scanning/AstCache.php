<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning;

use Illuminate\Filesystem\Filesystem;
use Throwable;

final class AstCache
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $path,
        private readonly bool $enabled = true,
    ) {
    }

    /**
     * @return array<int, \PhpParser\Node>|null
     */
    public function get(string $sourcePath, string $source): ?array
    {
        if (! $this->enabled || ! $this->isSafeCacheRoot()) {
            return null;
        }

        $cacheFile = $this->cacheFile($sourcePath, $source);
        if (! $this->files->isFile($cacheFile)) {
            return null;
        }

        try {
            $payload = $this->files->get($cacheFile);
            $ast = @unserialize($payload, ['allowed_classes' => true]);

            return is_array($ast) ? $ast : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, \PhpParser\Node> $ast
     */
    public function put(string $sourcePath, string $source, array $ast): void
    {
        if (! $this->enabled || ! $this->isSafeCacheRoot()) {
            return;
        }

        try {
            $this->files->ensureDirectoryExists($this->path);
            $this->files->put($this->cacheFile($sourcePath, $source), serialize($ast));
        } catch (Throwable) {
            // Cache failures must never change analysis correctness.
        }
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    private function cacheFile(string $sourcePath, string $source): string
    {
        return rtrim($this->path, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $this->cacheKey($sourcePath, $source)
            . '.cache';
    }

    private function cacheKey(string $sourcePath, string $source): string
    {
        $absolute = realpath($sourcePath) ?: $sourcePath;
        $mtime = is_file($absolute) ? (string) (@filemtime($absolute) ?: 0) : '0';

        return hash('sha256', $this->normalizePath($absolute) . '|' . $mtime . '|' . hash('sha256', $source));
    }

    private function isSafeCacheRoot(): bool
    {
        $root = $this->normalizePath($this->path);
        $projectRoot = $this->normalizePath(base_path());

        foreach (['src', 'tests', 'fixtures'] as $directory) {
            $forbidden = $projectRoot . '/' . $directory;
            if ($root === $forbidden || str_starts_with($root, $forbidden . '/')) {
                return false;
            }
        }

        return true;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
