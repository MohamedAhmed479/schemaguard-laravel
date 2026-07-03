<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

final readonly class SymbolTargetSet
{
    /**
     * @param array<string, true> $tables
     * @param array<string, array<string, true>> $columnsByTable
     */
    private function __construct(
        private array $tables,
        private array $columnsByTable,
    ) {
    }

    /**
     * @param SchemaChangeEvent[] $events
     */
    public static function fromEvents(array $events): self
    {
        $tables = [];
        $columnsByTable = [];

        foreach ($events as $event) {
            if (! $event instanceof SchemaChangeEvent) {
                continue;
            }

            if ($event->table !== null) {
                $tables[$event->table->table] = true;
            }

            if ($event->column !== null) {
                $tables[$event->column->table] = true;
                $columnsByTable[$event->column->table][$event->column->column] = true;
            }
        }

        return new self($tables, $columnsByTable);
    }

    public function hasTable(string $table): bool
    {
        return isset($this->tables[$table]);
    }

    public function hasColumn(string $table, string $column): bool
    {
        return isset($this->columnsByTable[$table][$column]);
    }

    public function hasColumnName(string $column): bool
    {
        foreach ($this->columnsByTable as $columns) {
            if (isset($columns[$column])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function columnsForTable(string $table): array
    {
        return array_keys($this->columnsByTable[$table] ?? []);
    }

    /**
     * @return string[]
     */
    public function tablesForColumn(string $column): array
    {
        $tables = [];

        foreach ($this->columnsByTable as $table => $columns) {
            if (isset($columns[$column])) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * @return string[]
     */
    public function tables(): array
    {
        return array_keys($this->tables);
    }

    /**
     * @return array<string, string[]>
     */
    public function columnsByTable(): array
    {
        $columns = [];

        foreach ($this->columnsByTable as $table => $tableColumns) {
            $columns[$table] = array_keys($tableColumns);
        }

        return $columns;
    }
}
