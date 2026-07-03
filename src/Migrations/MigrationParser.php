<?php

declare(strict_types=1);

namespace SchemaGuard\Migrations;

use Illuminate\Filesystem\Filesystem;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use SchemaGuard\Migrations\Visitors\SchemaCallVisitor;

final class MigrationParser
{
    private Parser $parser;

    /** @var string[] */
    private array $diagnostics = [];

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
            $events = array_merge($events, $this->parseFile($path));
        }

        return $events;
    }

    /**
     * @return array<int, \SchemaGuard\ValueObjects\SchemaChangeEvent>
     */
    public function parseFile(string $path): array
    {
        if (! $this->files->exists($path)) {
            $this->diagnostics[] = "Migration file not found: {$path}";

            return [];
        }

        try {
            $ast = $this->parser->parse($this->files->get($path));
        } catch (Error $error) {
            $this->diagnostics[] = "Could not parse migration {$path}: {$error->getMessage()}";

            return [];
        }

        if ($ast === null) {
            $this->diagnostics[] = "Could not parse migration {$path}: parser returned no AST.";

            return [];
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor(new ParentConnectingVisitor());

        $collector = new SchemaCallVisitor($path);
        $traverser->addVisitor($collector);
        $traverser->traverse($ast);

        return $collector->events();
    }
}
