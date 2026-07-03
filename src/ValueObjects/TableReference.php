<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

final readonly class TableReference
{
    public function __construct(public string $table)
    {
    }

    public function id(): string
    {
        return "table:{$this->table}";
    }
}
