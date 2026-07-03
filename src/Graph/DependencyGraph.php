<?php

declare(strict_types=1);

namespace SchemaGuard\Graph;

use InvalidArgumentException;

final class DependencyGraph
{
    /** @var array<string, GraphNode> */
    private array $nodes = [];

    /** @var array<string, array<string, true>> */
    private array $edges = [];

    public function addNode(GraphNode $node): void
    {
        $this->nodes[$node->id] ??= $node;
        $this->edges[$node->id] ??= [];
    }

    public function addEdge(string $fromId, string $toId): void
    {
        if (! isset($this->nodes[$fromId])) {
            throw new InvalidArgumentException("Cannot add edge from unknown graph node [{$fromId}].");
        }

        if (! isset($this->nodes[$toId])) {
            throw new InvalidArgumentException("Cannot add edge to unknown graph node [{$toId}].");
        }

        $this->edges[$fromId][$toId] = true;
    }

    /**
     * @return GraphNode[]
     */
    public function reachableFrom(string $id): array
    {
        if (! isset($this->nodes[$id])) {
            return [];
        }

        $visited = [$id => true];
        $queue = $this->sortedNeighbors($id);
        $reachable = [];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;
            $reachable[] = $this->nodes[$current];

            foreach ($this->sortedNeighbors($current) as $neighbor) {
                if (! isset($visited[$neighbor])) {
                    $queue[] = $neighbor;
                }
            }
        }

        return $reachable;
    }

    /**
     * @return ImpactPath[]
     */
    public function exposedPaths(string $columnId): array
    {
        if (! isset($this->nodes[$columnId])) {
            return [];
        }

        $paths = [];
        $this->collectExposedPaths($columnId, [$columnId], $paths);
        ksort($paths);

        return array_values($paths);
    }

    public function reachesExposedSurface(string $columnId): bool
    {
        return $this->exposedPaths($columnId) !== [];
    }

    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    public function node(string $id): ?GraphNode
    {
        return $this->nodes[$id] ?? null;
    }

    /**
     * @return array<string, GraphNode>
     */
    public function nodes(): array
    {
        ksort($this->nodes);

        return $this->nodes;
    }

    /**
     * @return array<string, string[]>
     */
    public function edges(): array
    {
        $edges = [];

        foreach ($this->edges as $from => $neighbors) {
            $ids = array_keys($neighbors);
            sort($ids);
            $edges[$from] = $ids;
        }

        ksort($edges);

        return $edges;
    }

    /**
     * @param string[] $path
     * @param array<string, ImpactPath> $paths
     */
    private function collectExposedPaths(string $current, array $path, array &$paths): void
    {
        $node = $this->nodes[$current];

        if (count($path) > 1 && in_array($node->type, [NodeType::ROUTE, NodeType::RESOURCE], true)) {
            $impactPath = new ImpactPath(array_map(fn (string $id): GraphNode => $this->nodes[$id], $path));
            $paths[$impactPath->id()] = $impactPath;

            return;
        }

        foreach ($this->sortedNeighbors($current) as $neighbor) {
            if (in_array($neighbor, $path, true)) {
                continue;
            }

            $this->collectExposedPaths($neighbor, [...$path, $neighbor], $paths);
        }
    }

    /**
     * @return string[]
     */
    private function sortedNeighbors(string $id): array
    {
        $neighbors = array_keys($this->edges[$id] ?? []);
        sort($neighbors);

        return $neighbors;
    }
}
