<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning;

use PhpParser\NodeTraverser;
use SchemaGuard\Scanning\Visitors\AbstractUsageVisitor;
use SchemaGuard\Scanning\Visitors\ApiResourceVisitor;
use SchemaGuard\Scanning\Visitors\ControllerVisitor;
use SchemaGuard\Scanning\Visitors\EloquentModelVisitor;
use SchemaGuard\Scanning\Visitors\EloquentUsageVisitor;
use SchemaGuard\Scanning\Visitors\RawSqlVisitor;
use SchemaGuard\ValueObjects\SymbolTargetSet;
use SchemaGuard\ValueObjects\Usage;

final class StaticAnalysisScanner
{
    private readonly LocalTypeResolver $typeResolver;

    private readonly ColumnTokenMatcher $tokenMatcher;

    private readonly ModelTableMap $modelTableMap;

    /** @var string[] */
    private array $diagnostics = [];

    public function __construct(
        ?LocalTypeResolver $typeResolver = null,
        ?ColumnTokenMatcher $tokenMatcher = null,
        ?ModelTableMap $modelTableMap = null,
    ) {
        $this->typeResolver = $typeResolver ?? new LocalTypeResolver();
        $this->tokenMatcher = $tokenMatcher ?? new ColumnTokenMatcher();
        $this->modelTableMap = $modelTableMap ?? new ModelTableMap();
    }

    /**
     * @param array<string, ParsedFile> $index
     * @param array<int, \SchemaGuard\ValueObjects\SchemaChangeEvent>|SymbolTargetSet $events
     *
     * @return Usage[]
     */
    public function scan(array $index, array|SymbolTargetSet $events): array
    {
        $this->diagnostics = [];
        $targets = $events instanceof SymbolTargetSet ? $events : SymbolTargetSet::fromEvents($events);

        $this->registerModels($index, $targets);

        $usages = [];

        foreach ($index as $file) {
            if (! $file->parsed || $file->ast === null) {
                continue;
            }

            foreach ($this->visitors() as $visitor) {
                $usages = array_merge($usages, $this->runVisitor($visitor, $file, $targets));
            }
        }

        return $this->dedupe($usages);
    }

    public function modelTableMap(): ModelTableMap
    {
        return $this->modelTableMap;
    }

    /**
     * @return string[]
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * @return AbstractUsageVisitor[]
     */
    private function visitors(): array
    {
        return [
            EloquentModelVisitor::usage($this->modelTableMap),
            new EloquentUsageVisitor($this->modelTableMap, $this->typeResolver, $this->tokenMatcher),
            new ApiResourceVisitor($this->modelTableMap),
            new ControllerVisitor(),
            new RawSqlVisitor($this->tokenMatcher),
        ];
    }

    /**
     * @param array<string, ParsedFile> $index
     */
    private function registerModels(array $index, SymbolTargetSet $targets): void
    {
        for ($pass = 0; $pass < 2; $pass++) {
            foreach ($index as $file) {
                if (! $file->parsed || $file->ast === null) {
                    continue;
                }

                $this->runVisitor(EloquentModelVisitor::registration($this->modelTableMap), $file, $targets);
            }
        }
    }

    /**
     * @return Usage[]
     */
    private function runVisitor(AbstractUsageVisitor $visitor, ParsedFile $file, SymbolTargetSet $targets): array
    {
        $visitor->reset($file, $targets);
        (new NodeTraverser($visitor))->traverse($file->ast ?? []);
        $this->diagnostics = array_merge($this->diagnostics, $visitor->diagnostics());

        return $visitor->usages();
    }

    /**
     * @param Usage[] $usages
     *
     * @return Usage[]
     */
    private function dedupe(array $usages): array
    {
        $deduped = [];

        foreach ($usages as $usage) {
            $key = $usage->symbol->id() . '|' . $usage->location->file . ':' . $usage->location->line;

            if (! isset($deduped[$key]) || $usage->confidence->atLeast($deduped[$key]->confidence)) {
                $deduped[$key] = $usage;
            }
        }

        return array_values($deduped);
    }
}
