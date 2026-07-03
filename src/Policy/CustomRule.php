<?php

declare(strict_types=1);

namespace SchemaGuard\Policy;

use SchemaGuard\ValueObjects\ChangeType;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\Severity;

final readonly class CustomRule
{
    public function __construct(
        public ?ChangeType $changeType,
        public ?string $table,
        public ?string $column,
        public Severity $severity,
    ) {
    }

    public function matches(SchemaChangeEvent $event): bool
    {
        if ($this->changeType !== null && $this->changeType !== $event->type) {
            return false;
        }

        if ($this->table !== null && $this->table !== $this->eventTable($event)) {
            return false;
        }

        if ($this->column !== null && $this->column !== $event->column?->column) {
            return false;
        }

        return true;
    }

    private function eventTable(SchemaChangeEvent $event): ?string
    {
        return $event->column?->table ?? $event->table?->table;
    }
}
