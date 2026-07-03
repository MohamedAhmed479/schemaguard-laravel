<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning;

use Illuminate\Support\Str;

final class ModelTableMap
{
    /** @var array<string, string> */
    private array $tablesByModel = [];

    /** @var array<string, array<string, string>> */
    private array $relationsByModel = [];

    public function register(string $fqcn, ?string $table = null): void
    {
        $fqcn = ltrim($fqcn, '\\');
        $this->tablesByModel[$fqcn] = $table ?? $this->defaultTableFor($fqcn);
    }

    public function registerRelation(string $modelFqcn, string $relation, string $relatedModelFqcn): void
    {
        $this->relationsByModel[ltrim($modelFqcn, '\\')][$relation] = ltrim($relatedModelFqcn, '\\');
    }

    public function hasModel(string $fqcn): bool
    {
        return isset($this->tablesByModel[ltrim($fqcn, '\\')]);
    }

    public function tableForModel(string $fqcn): ?string
    {
        return $this->tablesByModel[ltrim($fqcn, '\\')] ?? null;
    }

    public function relatedModelFor(string $modelFqcn, string $relation): ?string
    {
        return $this->relationsByModel[ltrim($modelFqcn, '\\')][$relation] ?? null;
    }

    /**
     * @return string[]
     */
    public function modelsForTable(string $table): array
    {
        return array_keys(array_filter(
            $this->tablesByModel,
            static fn (string $mappedTable): bool => $mappedTable === $table,
        ));
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->tablesByModel;
    }

    private function defaultTableFor(string $fqcn): string
    {
        $class = class_basename($fqcn);

        return Str::snake(Str::pluralStudly($class));
    }
}
