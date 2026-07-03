<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

final readonly class Usage
{
    public function __construct(
        public ColumnReference|TableReference $symbol,
        public SurfaceType $surface,
        public Confidence $confidence,
        public SourceLocation $location,
        public string $detail = '',
    ) {
    }
}
