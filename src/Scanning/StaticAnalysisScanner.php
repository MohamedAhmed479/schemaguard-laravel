<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning;

use PhpParser\NodeTraverser;
use SchemaGuard\Scanning\Visitors\AbstractUsageVisitor;
use SchemaGuard\Scanning\Visitors\ApiResourceVisitor;
use SchemaGuard\Scanning\Visitors\ControllerVisitor;
use SchemaGuard\Scanning\Visitors\EloquentModelVisitor;
use SchemaGuard\Scanning\Visitors\EloquentUsageVisitor;
use SchemaGuard\ValueObjects\SymbolTargetSet;
use SchemaGuard\ValueObjects\Usage;

final class StaticAnalysisScanner
{
    private readonly LocalTypeResolver $typeResolver;

    private readonly ColumnTokenMatcher $tokenMatcher;

    private readonly ModelTableMap $modelTableMap;

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
        $targets = $events instanceof SymbolTargetSet ? $events : SymbolTargetSet::fromEvents($events);

        foreach ($index as $file) {
            if (! $file->parsed || $file->ast === null) {
                continue;
            }

            $this->runVisitor(EloquentModelVisitor::registration($this->modelTableMap), $file, $targets);
        }

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
     * @return AbstractUsageVisitor[]
     */
    private function visitors(): array
    {
        return [
            EloquentModelVisitor::usage($this->modelTableMap),
            new EloquentUsageVisitor($this->modelTableMap, $this->typeResolver, $this->tokenMatcher),
            new ApiResourceVisitor($this->modelTableMap),
            new ControllerVisitor(),
        ];
    }

    /**
     * @return Usage[]
     */
    private function runVisitor(AbstractUsageVisitor $visitor, ParsedFile $file, SymbolTargetSet $targets): array
    {
        $visitor->reset($file, $targets);
        (new NodeTraverser($visitor))->traverse($file->ast ?? []);

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
