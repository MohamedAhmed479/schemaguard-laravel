<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

final readonly class SchemaChangeEvent
{
    public function __construct(
        public ChangeType $type,
        public ?TableReference $table,
        public ?ColumnReference $column,
        public SourceLocation $location,
        public ?string $renamedTo = null,
        public ?string $newType = null,
        public bool $indeterminate = false,
        public ?string $reason = null,
    ) {
    }

    public static function columnDropped(ColumnReference $column, SourceLocation $location): self
    {
        return new self(
            ChangeType::COLUMN_DROPPED,
            new TableReference($column->table),
            $column,
            $location,
        );
    }

    public static function columnRenamed(ColumnReference $column, ?string $renamedTo, SourceLocation $location): self
    {
        return new self(
            ChangeType::COLUMN_RENAMED,
            new TableReference($column->table),
            $column,
            $location,
            renamedTo: $renamedTo,
            indeterminate: $renamedTo === null,
            reason: $renamedTo === null ? 'dynamic new column name' : null,
        );
    }

    public static function tableDropped(TableReference $table, SourceLocation $location): self
    {
        return new self(
            ChangeType::TABLE_DROPPED,
            $table,
            null,
            $location,
        );
    }

    public static function indeterminate(
        ChangeType $type,
        ?TableReference $table,
        string $reason,
        SourceLocation $location,
    ): self {
        return new self(
            $type,
            $table,
            null,
            $location,
            indeterminate: true,
            reason: $reason,
        );
    }
}
