<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

use PhpParser\Node;

final readonly class SourceLocation
{
    public function __construct(
        public string $file,
        public int $line,
        public ?int $column = null,
    ) {
    }

    public static function fromNode(string $file, Node $node): self
    {
        return new self($file, $node->getStartLine());
    }
}
