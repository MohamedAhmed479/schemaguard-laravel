<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

enum Severity: int
{
    case SAFE = 0;
    case WARNING = 1;
    case BLOCK = 2;

    public function atLeast(self $floor): bool
    {
        return $this->value >= $floor->value;
    }
}
