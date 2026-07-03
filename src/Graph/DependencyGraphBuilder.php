<?php

declare(strict_types=1);

namespace SchemaGuard\Graph;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use SchemaGuard\Scanning\ModelTableMap;
use SchemaGuard\Scanning\ParsedFile;
use SchemaGuard\Scanning\Visitors\EloquentModelVisitor;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\RouteBinding;
use SchemaGuard\ValueObjects\SourceLocation;
use SchemaGuard\ValueObjects\SurfaceType;
use SchemaGuard\ValueObjects\TableReference;
use SchemaGuard\ValueObjects\Usage;

final class DependencyGraphBuilder
{
    /**
     * @param array<string, ParsedFile> $index
     * @param Usage[] $usages
     * @param RouteBinding[] $routeBindings
     */
    public function build(array $index, array $usages, array $routeBindings): DependencyGraph
    {
        $graph = new DependencyGraph();
        $modelTableMap = $this->buildModelTableMap($index);

        $this->addModels($graph, $modelTableMap);
        $this->addUsages($graph, $index, $modelTableMap, $usages);
        $this->addRoutes($graph, $routeBindings);

        return $graph;
    }

    /**
     * @param array<string, ParsedFile> $index
     */
    private function buildModelTableMap(array $index): ModelTableMap
    {
        $map = new ModelTableMap();

        for ($pass = 0; $pass < 2; $pass++) {
            foreach ($index as $file) {
                if (! $file instanceof ParsedFile || ! $file->parsed || $file->ast === null) {
                    continue;
                }

                (new NodeTraverser(EloquentModelVisitor::registration($map)))->traverse($file->ast);
            }
        }

        return $map;
    }

    private function addModels(DependencyGraph $graph, ModelTableMap $modelTableMap): void
    {
        foreach ($modelTableMap->all() as $model => $table) {
            $modelNode = GraphNode::model($model);
            $tableNode = GraphNode::table(new TableReference($table));

            $graph->addNode($modelNode);
            $graph->addNode($tableNode);
            $graph->addEdge($modelNode->id, $tableNode->id);
        }
    }

    /**
     * @param array<string, ParsedFile> $index
     * @param Usage[] $usages
     */
    private function addUsages(DependencyGraph $graph, array $index, ModelTableMap $modelTableMap, array $usages): void
    {
        foreach ($usages as $usage) {
            if (! $usage instanceof Usage) {
                continue;
            }

            if ($usage->symbol instanceof TableReference) {
                $graph->addNode(GraphNode::table($usage->symbol, $usage->location));

                continue;
            }

            $this->addColumnUsage($graph, $index, $modelTableMap, $usage);
        }
    }

    /**
     * @param array<string, ParsedFile> $index
     */
    private function addColumnUsage(
        DependencyGraph $graph,
        array $index,
        ModelTableMap $modelTableMap,
        Usage $usage,
    ): void {
        /** @var ColumnReference $column */
        $column = $usage->symbol;
        $columnNode = GraphNode::column($column, $usage->location);
        $tableNode = GraphNode::table(new TableReference($column->table));

        $graph->addNode($columnNode);
        $graph->addNode($tableNode);
        $graph->addEdge($columnNode->id, $tableNode->id);

        foreach ($modelTableMap->modelsForTable($column->table) as $model) {
            $modelNode = GraphNode::model($model);
            $graph->addNode($modelNode);
            $graph->addEdge($columnNode->id, $modelNode->id);
        }

        if ($usage->surface === SurfaceType::API_RESOURCE) {
            $this->addResourceUsage($graph, $index, $modelTableMap, $usage, $columnNode);

            return;
        }

        if (in_array($usage->surface, [SurfaceType::CONTROLLER, SurfaceType::ELOQUENT_QUERY], true)) {
            $this->addControllerUsage($graph, $index, $modelTableMap, $usage, $column);
        }
    }

    /**
     * @param array<string, ParsedFile> $index
     */
    private function addResourceUsage(
        DependencyGraph $graph,
        array $index,
        ModelTableMap $modelTableMap,
        Usage $usage,
        GraphNode $columnNode,
    ): void {
        $class = $this->classAt($index, $usage->location);

        if ($class === null) {
            return;
        }

        $resourceNode = GraphNode::resource($class, $usage->location);
        $graph->addNode($resourceNode);
        $graph->addEdge($columnNode->id, $resourceNode->id);

        $model = $this->modelForResource($class, $modelTableMap);
        if ($model === null) {
            return;
        }

        $modelNode = GraphNode::model($model);
        $graph->addNode($modelNode);
        $graph->addEdge($resourceNode->id, $modelNode->id);
    }

    /**
     * @param array<string, ParsedFile> $index
     */
    private function addControllerUsage(
        DependencyGraph $graph,
        array $index,
        ModelTableMap $modelTableMap,
        Usage $usage,
        ColumnReference $column,
    ): void {
        $action = $this->actionAt($index, $usage->location);

        if ($action === null) {
            return;
        }

        $actionNode = GraphNode::action($action['class'], $action['method'], $usage->location);
        $controllerNode = GraphNode::controller($action['class'], $usage->location);

        $graph->addNode($actionNode);
        $graph->addNode($controllerNode);
        $graph->addEdge($actionNode->id, $controllerNode->id);

        foreach ($modelTableMap->modelsForTable($column->table) as $model) {
            $modelNode = GraphNode::model($model);
            $graph->addNode($modelNode);
            $graph->addEdge($modelNode->id, $actionNode->id);
            $graph->addEdge($controllerNode->id, $modelNode->id);
        }
    }

    /**
     * @param RouteBinding[] $routeBindings
     */
    private function addRoutes(DependencyGraph $graph, array $routeBindings): void
    {
        foreach ($routeBindings as $binding) {
            if (! $binding instanceof RouteBinding) {
                continue;
            }

            $routeNode = GraphNode::route($binding->verb, $binding->uri, $binding->location);
            $actionNode = GraphNode::action($binding->controllerFqcn, $binding->method, $binding->location);
            $controllerNode = GraphNode::controller($binding->controllerFqcn, $binding->location);

            $graph->addNode($routeNode);
            $graph->addNode($actionNode);
            $graph->addNode($controllerNode);
            $graph->addEdge($routeNode->id, $actionNode->id);
            $graph->addEdge($actionNode->id, $routeNode->id);
            $graph->addEdge($actionNode->id, $controllerNode->id);
        }
    }

    /**
     * @param array<string, ParsedFile> $index
     *
     * @return array{class:string,method:string}|null
     */
    private function actionAt(array $index, SourceLocation $location): ?array
    {
        $file = $this->fileForLocation($index, $location);

        if ($file === null || $file->ast === null) {
            return null;
        }

        $method = (new NodeFinder())->findFirst($file->ast, static function (Node $node) use ($location): bool {
            return $node instanceof ClassMethod
                && $node->getStartLine() <= $location->line
                && $node->getEndLine() >= $location->line;
        });

        if (! $method instanceof ClassMethod) {
            return null;
        }

        $class = $this->enclosingClass($method);
        $classFqcn = $class === null ? null : $this->classFqcn($class);

        return $classFqcn === null ? null : ['class' => $classFqcn, 'method' => $method->name->toString()];
    }

    /**
     * @param array<string, ParsedFile> $index
     */
    private function classAt(array $index, SourceLocation $location): ?string
    {
        $file = $this->fileForLocation($index, $location);

        if ($file === null || $file->ast === null) {
            return null;
        }

        $class = (new NodeFinder())->findFirst($file->ast, static function (Node $node) use ($location): bool {
            return $node instanceof Class_
                && $node->getStartLine() <= $location->line
                && $node->getEndLine() >= $location->line;
        });

        return $class instanceof Class_ ? $this->classFqcn($class) : null;
    }

    /**
     * @param array<string, ParsedFile> $index
     */
    private function fileForLocation(array $index, SourceLocation $location): ?ParsedFile
    {
        foreach ($index as $path => $file) {
            if ($file instanceof ParsedFile && $this->samePath($path, $location->file)) {
                return $file;
            }
        }

        return null;
    }

    private function modelForResource(string $resourceFqcn, ModelTableMap $modelTableMap): ?string
    {
        $base = class_basename($resourceFqcn);
        $candidate = preg_replace('/(Resource|Collection)$/', '', $base) ?: $base;

        foreach (array_keys($modelTableMap->all()) as $model) {
            if (class_basename($model) === $candidate) {
                return $model;
            }
        }

        return null;
    }

    private function enclosingClass(Node $node): ?Class_
    {
        $cursor = $node;

        while (($parent = $cursor->getAttribute('parent')) instanceof Node) {
            if ($parent instanceof Class_) {
                return $parent;
            }

            $cursor = $parent;
        }

        return null;
    }

    private function classFqcn(Class_ $class): ?string
    {
        return isset($class->namespacedName) ? $class->namespacedName->toString() : null;
    }

    private function samePath(string $left, string $right): bool
    {
        $left = realpath($left) ?: $left;
        $right = realpath($right) ?: $right;

        return str_replace('\\', '/', $left) === str_replace('\\', '/', $right);
    }
}
