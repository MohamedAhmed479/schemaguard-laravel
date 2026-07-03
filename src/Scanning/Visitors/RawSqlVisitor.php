<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning\Visitors;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use SchemaGuard\Scanning\ColumnTokenMatcher;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SurfaceType;
use SchemaGuard\ValueObjects\Usage;

final class RawSqlVisitor extends AbstractUsageVisitor
{
    private const DB_METHODS = [
        'select',
        'statement',
        'update',
        'insert',
        'raw',
    ];

    private const BUILDER_METHODS = [
        'whereRaw',
        'selectRaw',
        'havingRaw',
        'orderByRaw',
        'groupByRaw',
    ];

    public function __construct(private readonly ColumnTokenMatcher $matcher = new ColumnTokenMatcher())
    {
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof StaticCall && $this->isDbFacadeCall($node)) {
            $method = $this->methodName($node);
            if ($method !== null && in_array($method, self::DB_METHODS, true)) {
                $this->inspectRawArgument($node->args[0]->value ?? null, $node, $method);
            }

            return null;
        }

        if ($node instanceof MethodCall) {
            $method = $this->methodName($node);
            if ($method !== null && in_array($method, self::BUILDER_METHODS, true)) {
                $this->inspectRawArgument($node->args[0]->value ?? null, $node, $method);
            }
        }

        return null;
    }

    private function inspectRawArgument(?Expr $expr, Node $node, string $method): void
    {
        if (! $expr instanceof String_) {
            $location = $this->location($node);
            $this->addDiagnostic(sprintf(
                'Indeterminate raw SQL at %s:%d in %s(): dynamic SQL string requires manual review.',
                $location->file,
                $location->line,
                $method,
            ));

            return;
        }

        $this->scanSql($expr->value, $node, $method);
    }

    private function scanSql(string $sql, Node $node, string $method): void
    {
        foreach ($this->targets?->columnsByTable() ?? [] as $table => $columns) {
            foreach ($columns as $column) {
                $qualified = "{$table}.{$column}";

                if ($this->matcher->matchesInSql($sql, $qualified)) {
                    $this->emitUsage($table, $column, Confidence::HIGH, $node, $method);
                    continue;
                }

                if ($this->matcher->matchesInSql($sql, $column)) {
                    $this->emitUsage($table, $column, Confidence::MEDIUM, $node, $method);
                }
            }
        }
    }

    private function emitUsage(string $table, string $column, Confidence $confidence, Node $node, string $method): void
    {
        $this->emit(new Usage(
            new ColumnReference($table, $column),
            SurfaceType::RAW_SQL,
            $confidence,
            $this->location($node),
            $method . '()',
        ));
    }

    private function isDbFacadeCall(StaticCall $node): bool
    {
        if (! $node->class instanceof Name) {
            return false;
        }

        $resolvedName = $node->class->getAttribute('resolvedName');
        $class = ltrim(($resolvedName instanceof Name ? $resolvedName : $node->class)->toString(), '\\');

        return in_array($class, ['DB', 'Illuminate\Support\Facades\DB'], true);
    }

    private function methodName(MethodCall|StaticCall $node): ?string
    {
        return $node->name instanceof Node\Identifier ? $node->name->toString() : null;
    }
}
