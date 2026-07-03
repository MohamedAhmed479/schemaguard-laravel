<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning\Visitors;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use SchemaGuard\Scanning\ColumnTokenMatcher;
use SchemaGuard\Scanning\LocalTypeResolver;
use SchemaGuard\Scanning\ModelTableMap;
use SchemaGuard\Scanning\ResolvedType;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SurfaceType;
use SchemaGuard\ValueObjects\Usage;

final class EloquentUsageVisitor extends AbstractUsageVisitor
{
    public function __construct(
        private readonly ModelTableMap $modelTableMap,
        private readonly LocalTypeResolver $typeResolver,
        private readonly ColumnTokenMatcher $tokenMatcher,
    ) {
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof PropertyFetch) {
            $this->handlePropertyFetch($node);
        }

        if ($node instanceof StaticCall || $node instanceof MethodCall) {
            $this->handleBuilderCall($node);
        }

        return null;
    }

    private function handlePropertyFetch(PropertyFetch $node): void
    {
        if (! $node->name instanceof Identifier || ! $this->targets?->hasColumnName($node->name->toString())) {
            return;
        }

        if ($node->var instanceof Variable && in_array($node->var->name, ['this', 'request'], true)) {
            return;
        }

        $column = $node->name->toString();
        $resolved = $node->var instanceof Expr
            ? $this->typeResolver->resolveExpression($node->var, $node, $this->modelTableMap)
            : ResolvedType::unknown();
        $table = $this->tableForResolvedType($resolved);

        $this->emitColumnUsage($table, $column, $node, 'property access');
    }

    private function handleBuilderCall(StaticCall|MethodCall $node): void
    {
        $method = $this->methodName($node);

        if ($method === null || ! array_key_exists($method, $this->builderColumnMethods())) {
            return;
        }

        $columns = $this->columnsFromArguments($node->args, $this->builderColumnMethods()[$method]);
        if ($columns === []) {
            return;
        }

        $table = $this->receiverTable($node);

        foreach ($columns as $column) {
            $this->emitColumnUsage($table, $column, $node, $method . '()');
        }
    }

    private function receiverTable(StaticCall|MethodCall $node): ?string
    {
        if ($node instanceof StaticCall) {
            if (! $node->class instanceof Name) {
                return null;
            }

            $model = $this->resolveName($node->class);

            return $this->modelTableMap->tableForModel($model);
        }

        $resolved = $this->typeResolver->resolveExpression($node->var, $node, $this->modelTableMap);

        return $this->tableForResolvedType($resolved);
    }

    private function tableForResolvedType(ResolvedType $resolved): ?string
    {
        if ($resolved->isTable()) {
            return $resolved->name;
        }

        if ($resolved->isModel() && $resolved->name !== null) {
            return $this->modelTableMap->tableForModel($resolved->name);
        }

        return null;
    }

    private function emitColumnUsage(?string $table, string $column, Node $node, string $detail): void
    {
        if ($table !== null) {
            if (! $this->targets?->hasColumn($table, $column)) {
                return;
            }

            $this->emit(new Usage(
                new ColumnReference($table, $column),
                SurfaceType::ELOQUENT_QUERY,
                Confidence::DEFINITIVE,
                $this->location($node),
                $detail,
            ));

            return;
        }

        foreach ($this->targets?->tablesForColumn($column) ?? [] as $targetTable) {
            $this->emit(new Usage(
                new ColumnReference($targetTable, $column),
                SurfaceType::ELOQUENT_QUERY,
                $this->tokenMatcher->confidenceForUnresolved($column),
                $this->location($node),
                $detail . ' unresolved receiver',
            ));
        }
    }

    /**
     * @param Arg[] $args
     * @param int[]|string $positions
     *
     * @return string[]
     */
    private function columnsFromArguments(array $args, array|string $positions): array
    {
        $columns = [];

        if ($positions === 'all') {
            foreach ($args as $arg) {
                $columns = array_merge($columns, $this->stringsFromExpression($arg->value));
            }

            return array_values(array_unique($columns));
        }

        foreach ($positions as $position) {
            if (isset($args[$position])) {
                $columns = array_merge($columns, $this->stringsFromExpression($args[$position]->value));
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @return string[]
     */
    private function stringsFromExpression(Expr $expr): array
    {
        if ($expr instanceof String_) {
            return [$expr->value];
        }

        if (! $expr instanceof Array_) {
            return [];
        }

        $strings = [];

        foreach ($expr->items as $item) {
            if ($item?->value instanceof String_) {
                $strings[] = $item->value->value;
            }
        }

        return $strings;
    }

    /**
     * @return array<string, int[]|string>
     */
    private function builderColumnMethods(): array
    {
        return config('schemaguard.builder_column_methods', [
            'where' => [0],
            'orWhere' => [0],
            'select' => 'all',
            'addSelect' => 'all',
            'orderBy' => [0],
            'orderByDesc' => [0],
            'groupBy' => 'all',
        ]);
    }

    private function methodName(StaticCall|MethodCall $node): ?string
    {
        return $node->name instanceof Identifier ? $node->name->toString() : null;
    }
}
