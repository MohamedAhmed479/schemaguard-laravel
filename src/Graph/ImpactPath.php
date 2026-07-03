<?php

declare(strict_types=1);

namespace SchemaGuard\Graph;

use InvalidArgumentException;

final readonly class ImpactPath
{
    /** @var GraphNode[] */
    public array $nodes;

    /**
     * @param GraphNode[] $nodes
     */
    public function __construct(array $nodes)
    {
        if ($nodes === []) {
            throw new InvalidArgumentException('ImpactPath requires at least one graph node.');
        }

        foreach ($nodes as $node) {
            if (! $node instanceof GraphNode) {
                throw new InvalidArgumentException('ImpactPath nodes must be GraphNode instances.');
            }
        }

        $this->nodes = array_values($nodes);
    }

    public function id(): string
    {
        return implode('>', array_map(static fn (GraphNode $node): string => $node->id, $this->nodes));
    }

    public function __toString(): string
    {
        return implode(' → ', array_map(static fn (GraphNode $node): string => $node->label, $this->nodes));
    }
}
