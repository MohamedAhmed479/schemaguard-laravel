<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use Illuminate\Filesystem\Filesystem;
use PhpParser\NodeTraverser;
use SchemaGuard\Scanning\CodebaseIndexer;
use SchemaGuard\Scanning\ModelTableMap;
use SchemaGuard\Scanning\ParsedFile;
use SchemaGuard\Scanning\Visitors\AbstractUsageVisitor;
use SchemaGuard\Scanning\Visitors\EloquentModelVisitor;
use SchemaGuard\Tests\TestCase;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\SourceLocation;
use SchemaGuard\ValueObjects\SymbolTargetSet;
use SchemaGuard\ValueObjects\Usage;

abstract class ScanningTestCase extends TestCase
{
    /**
     * @param string[] $paths
     *
     * @return array<string, ParsedFile>
     */
    protected function index(array $paths): array
    {
        return (new CodebaseIndexer(new Filesystem(), [
            'ignore_paths' => [
                '*/Ignored/*',
            ],
        ]))->index(array_map(fn (string $path): string => $this->fixture($path), $paths));
    }

    protected function fixture(string $path): string
    {
        $fullPath = __DIR__ . '/../../Fixtures/' . str_replace('/', DIRECTORY_SEPARATOR, $path);

        return realpath($fullPath) ?: $fullPath;
    }

    protected function targetEvent(string $symbol): SchemaChangeEvent
    {
        [$table, $column] = explode('.', $symbol, 2);

        return SchemaChangeEvent::columnDropped(
            new ColumnReference($table, $column),
            new SourceLocation('migration.php', 1),
        );
    }

    protected function targets(string $symbol): SymbolTargetSet
    {
        return SymbolTargetSet::fromEvents([$this->targetEvent($symbol)]);
    }

    protected function modelTableMap(): ModelTableMap
    {
        $map = new ModelTableMap();

        foreach ($this->index(['Models']) as $file) {
            if (! $file->parsed || $file->ast === null) {
                continue;
            }

            (new NodeTraverser(EloquentModelVisitor::registration($map)))->traverse($file->ast);
        }

        return $map;
    }

    /**
     * @return Usage[]
     */
    protected function runVisitor(AbstractUsageVisitor $visitor, ParsedFile $file, string $target): array
    {
        $visitor->reset($file, $this->targets($target));
        (new NodeTraverser($visitor))->traverse($file->ast ?? []);

        return $visitor->usages();
    }
}
