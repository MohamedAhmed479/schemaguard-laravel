<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

final readonly class ColumnReference
{
    public function __construct(
        public string $table,
        public string $column,
    ) {
    }

    public function id(): string
    {
        return "column:{$this->table}.{$this->column}";
    }

    public function equals(self $other): bool
    {
        return $this->table === $other->table && $this->column === $other->column;
    }
}
