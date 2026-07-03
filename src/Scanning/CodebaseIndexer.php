<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use SplFileInfo;

final class CodebaseIndexer
{
    private Parser $parser;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly Filesystem $files,
        private readonly array $config = [],
    ) {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @param string[]|null $scanPaths
     *
     * @return array<string, ParsedFile>
     */
    public function index(?array $scanPaths = null): array
    {
        $paths = $scanPaths
            ?? $this->config['scan_paths']
            ?? config('schemaguard.scan_paths', ['app']);

        if (is_string($paths)) {
            $paths = [$paths];
        }

        $index = [];

        foreach ($this->discoverPhpFiles($paths) as $path) {
            if ($this->isIgnored($path)) {
                continue;
            }

            $index[$path] = $this->parse($path);
        }

        ksort($index);

        return $index;
    }

    private function parse(string $path): ParsedFile
    {
        try {
            $source = $this->files->get($path);
            $ast = $this->parser->parse($source) ?? [];
            $traverser = new NodeTraverser(
                new NameResolver(),
                new ParentConnectingVisitor(),
            );

            return ParsedFile::parsed($path, $traverser->traverse($ast));
        } catch (FileNotFoundException|Error $exception) {
            return ParsedFile::failed($path, $exception->getMessage());
        }
    }

    /**
     * @param array<int, mixed> $scanPaths
     *
     * @return string[]
     */
    private function discoverPhpFiles(array $scanPaths): array
    {
        $paths = [];

        foreach ($scanPaths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $absolute = $this->absolutePath($path);

            if ($this->files->isFile($absolute) && strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) === 'php') {
                $paths[] = $absolute;
                continue;
            }

            if (! $this->files->isDirectory($absolute)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach ($this->files->allFiles($absolute) as $file) {
                if (strtolower($file->getExtension()) === 'php') {
                    $paths[] = $file->getPathname();
                }
            }
        }

        sort($paths);

        return array_values(array_unique($paths));
    }

    private function isIgnored(string $path): bool
    {
        $patterns = $this->config['ignore_paths']
            ?? config('schemaguard.ignore_paths', []);

        if (is_string($patterns)) {
            $patterns = [$patterns];
        }

        $normalizedPath = $this->normalizePath($path);

        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            $normalizedPattern = $this->normalizePath($pattern);

            if (fnmatch($normalizedPattern, $normalizedPath, FNM_NOESCAPE)) {
                return true;
            }

            if (! str_starts_with($normalizedPattern, '*') && fnmatch('*/' . $normalizedPattern, $normalizedPath, FNM_NOESCAPE)) {
                return true;
            }
        }

        return false;
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

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
