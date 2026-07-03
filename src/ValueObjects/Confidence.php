<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

enum Confidence: int
{
    case LOW = 1;
    case MEDIUM = 2;
    case HIGH = 3;
    case DEFINITIVE = 4;

    public function atLeast(self $floor): bool
    {
        return $this->value >= $floor->value;
    }
}
