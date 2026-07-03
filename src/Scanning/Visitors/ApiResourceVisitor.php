<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning\Visitors;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use SchemaGuard\Scanning\ModelTableMap;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SurfaceType;
use SchemaGuard\ValueObjects\Usage;

final class ApiResourceVisitor extends AbstractUsageVisitor
{
    private const RESOURCE_BASES = [
        'Illuminate\Http\Resources\Json\JsonResource',
        'Illuminate\Http\Resources\Json\ResourceCollection',
    ];

    /** @var array<int, array{resource:bool,table:?string}> */
    private array $resourceStack = [];

    public function __construct(private readonly ModelTableMap $modelTableMap)
    {
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Class_) {
            $this->enterClass($node);

            return null;
        }

        if ($node instanceof PropertyFetch) {
            $this->handlePropertyFetch($node);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Class_ && $this->resourceStack !== []) {
            array_pop($this->resourceStack);
        }

        return null;
    }

    private function enterClass(Class_ $class): void
    {
        if (! $this->isResourceClass($class)) {
            $this->resourceStack[] = ['resource' => false, 'table' => null];

            return;
        }

        $this->resourceStack[] = ['resource' => true, 'table' => $this->associatedTable($class)];
    }

    private function handlePropertyFetch(PropertyFetch $node): void
    {
        if (! $this->insideResourceClass() || ! $this->isInsideToArray($node)) {
            return;
        }

        if (! $node->var instanceof Variable || $node->var->name !== 'this' || ! $node->name instanceof Identifier) {
            return;
        }

        $column = $node->name->toString();
        if (! $this->targets?->hasColumnName($column)) {
            return;
        }

        $table = $this->currentResourceTable();

        if ($table !== null && $this->targets->hasColumn($table, $column)) {
            $this->emit(new Usage(
                new ColumnReference($table, $column),
                SurfaceType::API_RESOURCE,
                Confidence::DEFINITIVE,
                $this->location($node),
                '$this->' . $column,
            ));

            return;
        }

        foreach ($this->targets->tablesForColumn($column) as $targetTable) {
            $this->emit(new Usage(
                new ColumnReference($targetTable, $column),
                SurfaceType::API_RESOURCE,
                Confidence::HIGH,
                $this->location($node),
                '$this->' . $column . ' resource fallback',
            ));
        }
    }

    private function isResourceClass(Class_ $class): bool
    {
        return $class->extends instanceof Name && in_array($this->resolveName($class->extends), self::RESOURCE_BASES, true);
    }

    private function associatedTable(Class_ $class): ?string
    {
        $doc = $class->getDocComment()?->getText() ?? '';

        if (preg_match('/@mixin\s+([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)/', $doc, $match) === 1) {
            $model = $this->matchKnownModel(ltrim($match[1], '\\'));
            if ($model !== null) {
                return $this->modelTableMap->tableForModel($model);
            }
        }

        $fqcn = $this->classFqcn($class);
        if ($fqcn === null) {
            return null;
        }

        $base = class_basename($fqcn);
        $modelBase = preg_replace('/(Resource|Collection)$/', '', $base) ?: $base;
        $model = $this->matchKnownModel($modelBase);

        return $model === null ? null : $this->modelTableMap->tableForModel($model);
    }

    private function matchKnownModel(string $fqcnOrShortName): ?string
    {
        foreach (array_keys($this->modelTableMap->all()) as $knownModel) {
            if ($knownModel === $fqcnOrShortName || class_basename($knownModel) === $fqcnOrShortName) {
                return $knownModel;
            }
        }

        return null;
    }

    private function isInsideToArray(Node $node): bool
    {
        $method = $this->enclosingMethod($node);

        return $method !== null && $method->name->toString() === 'toArray';
    }

    private function currentResourceTable(): ?string
    {
        $context = end($this->resourceStack);

        return is_array($context) ? $context['table'] : null;
    }

    private function insideResourceClass(): bool
    {
        $context = end($this->resourceStack);

        return is_array($context) && $context['resource'];
    }
}
