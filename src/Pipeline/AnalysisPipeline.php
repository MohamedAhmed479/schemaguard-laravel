<?php

declare(strict_types=1);

namespace SchemaGuard\Pipeline;

use PhpParser\NodeTraverser;
use SchemaGuard\Graph\DependencyGraphBuilder;
use SchemaGuard\Migrations\MigrationDiscovery;
use SchemaGuard\Migrations\MigrationParser;
use SchemaGuard\Policy\PolicyEngine;
use SchemaGuard\Policy\PolicyResult;
use SchemaGuard\Scanning\CodebaseIndexer;
use SchemaGuard\Scanning\ParsedFile;
use SchemaGuard\Scanning\StaticAnalysisScanner;
use SchemaGuard\Scanning\Visitors\RouteVisitor;
use SchemaGuard\ValueObjects\RouteBinding;

final readonly class AnalysisPipeline
{
    public function __construct(
        private MigrationDiscovery $discovery,
        private MigrationParser $migrationParser,
        private CodebaseIndexer $indexer,
        private StaticAnalysisScanner $scanner,
        private RouteVisitor $routeVisitor,
        private DependencyGraphBuilder $graphBuilder,
        private PolicyEngine $policy,
    ) {
    }

    public function run(AnalysisRequest $request, ?callable $progress = null): AnalysisRunResult
    {
        $migrationFiles = $this->discovery->resolve($request);
        $events = $this->migrationParser->parseMany($migrationFiles);
        $parserDiagnostics = $this->migrationParser->diagnostics();

        if ($events === []) {
            return new AnalysisRunResult(
                new PolicyResult([], $parserDiagnostics),
                new AnalysisMetadata(count($migrationFiles), 0, 0),
            );
        }

        $index = $this->indexer->index(
            $request->scanPaths,
            $progress,
            respectIgnorePaths: ! $request->scanPathsWereProvided,
            useCache: $request->useCache,
        );
        $indexDiagnostics = $this->indexDiagnostics($index);
        $usages = $this->scanner->scan($index, $events);
        $scannerDiagnostics = $this->scanner->diagnostics();
        $routeBindings = $this->routeBindings($index);
        $graph = $this->graphBuilder->build($index, $usages, $routeBindings);
        $policyResult = $this->policy->evaluate($events, $usages, $graph);

        return new AnalysisRunResult(
            new PolicyResult(
                $policyResult->findings,
                [
                    ...$parserDiagnostics,
                    ...$indexDiagnostics,
                    ...$scannerDiagnostics,
                    ...$policyResult->diagnostics,
                ],
            ),
            new AnalysisMetadata(
                count($migrationFiles),
                count($index),
                count($indexDiagnostics),
            ),
        );
    }

    /**
     * @param array<string, ParsedFile> $index
     *
     * @return RouteBinding[]
     */
    private function routeBindings(array $index): array
    {
        $bindings = [];

        foreach ($index as $file) {
            if (! $file->parsed || $file->ast === null) {
                continue;
            }

            $this->routeVisitor->reset($file);
            (new NodeTraverser($this->routeVisitor))->traverse($file->ast);
            $bindings = array_merge($bindings, $this->routeVisitor->bindings());
        }

        return $bindings;
    }

    /**
     * @param array<string, ParsedFile> $index
     *
     * @return string[]
     */
    private function indexDiagnostics(array $index): array
    {
        $diagnostics = [];

        foreach ($index as $file) {
            if (! $file->parsed) {
                $diagnostics[] = sprintf(
                    'Could not parse source %s: %s',
                    $file->path,
                    $file->error ?? 'unknown parse error',
                );
            }
        }

        return $diagnostics;
    }
}
