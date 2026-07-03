<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

final readonly class SourceLocation
{
    public function __construct(
        public string $file,
        public int $line,
        public ?int $column = null,
    ) {
    }
}
