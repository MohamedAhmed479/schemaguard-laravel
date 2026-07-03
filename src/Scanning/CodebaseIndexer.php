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
use SchemaGuard\Exceptions\CodebaseScanException;
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
        private readonly ?AstCache $cache = null,
    ) {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @param string[]|null $scanPaths
     *
     * @return array<string, ParsedFile>
     */
    public function index(
        ?array $scanPaths = null,
        ?callable $progress = null,
        bool $respectIgnorePaths = true,
        bool $useCache = true,
    ): array {
        $paths = $scanPaths
            ?? $this->config['scan_paths']
            ?? config('schemaguard.scan_paths', ['app']);

        if (is_string($paths)) {
            $paths = [$paths];
        }

        $index = [];
        $files = $this->discoverPhpFiles($paths);
        if ($progress !== null) {
            $progress('start', count($files));
        }

        foreach ($files as $path) {
            if ($respectIgnorePaths && $this->isIgnored($path)) {
                if ($progress !== null) {
                    $progress('advance', $path);
                }

                continue;
            }

            $index[$path] = $this->parse($path, $useCache);
            if ($progress !== null) {
                $progress('advance', $path);
            }
        }

        if ($progress !== null) {
            $progress('finish', null);
        }
        ksort($index);

        return $index;
    }

    private function parse(string $path, bool $useCache): ParsedFile
    {
        try {
            $source = $this->files->get($path);
            $ast = $useCache ? $this->cache?->get($path, $source) : null;

            if ($ast === null) {
                $ast = $this->parser->parse($source) ?? [];
                if ($useCache) {
                    $this->cache?->put($path, $source, $ast);
                }
            }

            return ParsedFile::parsed($path, $this->prepareAst($ast));
        } catch (FileNotFoundException|Error $exception) {
            return ParsedFile::failed($path, $exception->getMessage());
        }
    }

    /**
     * @param array<int, \PhpParser\Node> $ast
     *
     * @return array<int, \PhpParser\Node>
     */
    private function prepareAst(array $ast): array
    {
        $traverser = new NodeTraverser(
            new NameResolver(),
            new ParentConnectingVisitor(),
        );

        return $traverser->traverse($ast);
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
                throw new CodebaseScanException("Scan path does not exist: {$path}");
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

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
