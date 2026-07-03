<?php

declare(strict_types=1);

namespace SchemaGuard\Migrations;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use SchemaGuard\Exceptions\MigrationParseException;
use SchemaGuard\Migrations\Visitors\SchemaCallVisitor;

final class MigrationParser
{
    /** @var string[] */
    private array $diagnostics = [];

    private Parser $parser;

    public function __construct(private readonly Filesystem $files)
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return string[]
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * @param string[] $paths
     *
     * @return array<int, \SchemaGuard\ValueObjects\SchemaChangeEvent>
     */
    public function parseMany(array $paths): array
    {
        $this->diagnostics = [];
        $events = [];

        foreach ($paths as $path) {
            $events = array_merge($events, $this->parseSingleFile($path));
        }

        return $events;
    }

    /**
     * @return array<int, \SchemaGuard\ValueObjects\SchemaChangeEvent>
     */
    public function parseFile(string $path): array
    {
        $this->diagnostics = [];

        return $this->parseSingleFile($path);
    }

    /**
     * @return array<int, \SchemaGuard\ValueObjects\SchemaChangeEvent>
     */
    private function parseSingleFile(string $path): array
    {
        try {
            $source = $this->readMigration($path);
            $ast = $this->parser->parse($source) ?? [];
        } catch (MigrationParseException $exception) {
            $this->diagnostics[] = $exception->getMessage();

            return [];
        } catch (Error $exception) {
            $this->diagnostics[] = "Could not parse migration {$path}: {$exception->getMessage()}";

            return [];
        }

        $visitor = new SchemaCallVisitor($path);
        $traverser = new NodeTraverser(
            new NameResolver(),
            new ParentConnectingVisitor(),
            $visitor,
        );
        $traverser->traverse($ast);

        return $visitor->events();
    }

    private function readMigration(string $path): string
    {
        if (! $this->files->exists($path)) {
            throw new MigrationParseException("Migration file not found: {$path}");
        }

        try {
            return $this->files->get($path);
        } catch (FileNotFoundException $exception) {
            throw new MigrationParseException("Migration file not readable: {$path}", previous: $exception);
        }
    }
}
