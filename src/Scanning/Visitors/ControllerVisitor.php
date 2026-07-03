<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning\Visitors;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SurfaceType;
use SchemaGuard\ValueObjects\Usage;

final class ControllerVisitor extends AbstractUsageVisitor
{
    public function enterNode(Node $node)
    {
        if ($node instanceof MethodCall) {
            $this->handleMethodCall($node);
        }

        if ($node instanceof PropertyFetch) {
            $this->handleRequestProperty($node);
        }

        if ($node instanceof ClassMethod && $node->name->toString() === 'rules') {
            $this->handleRulesMethod($node);
        }

        return null;
    }

    private function handleMethodCall(MethodCall $call): void
    {
        $method = $this->methodName($call);

        if ($method === 'validate' && ($call->args[0]->value ?? null) instanceof Array_) {
            foreach ($this->arrayStringKeys($call->args[0]->value) as $column) {
                $this->emitForAllTargetTables($column, $call, Confidence::HIGH, 'validate()');
            }

            return;
        }

        if (in_array($method, ['input', 'get'], true) && ($call->args[0]->value ?? null) instanceof String_) {
            $this->emitForAllTargetTables($call->args[0]->value->value, $call, Confidence::MEDIUM, $method . '()');

            return;
        }

        if ($method === 'only' && ($call->args[0]->value ?? null) instanceof Array_) {
            foreach ($this->arrayStringValues($call->args[0]->value) as $column) {
                $this->emitForAllTargetTables($column, $call, Confidence::MEDIUM, 'only()');
            }
        }
    }

    private function handleRequestProperty(PropertyFetch $fetch): void
    {
        if (! $fetch->var instanceof Variable || $fetch->var->name !== 'request' || ! $fetch->name instanceof Identifier) {
            return;
        }

        $this->emitForAllTargetTables($fetch->name->toString(), $fetch, Confidence::MEDIUM, '$request property');
    }

    private function handleRulesMethod(ClassMethod $method): void
    {
        foreach ($method->stmts ?? [] as $statement) {
            if (! $statement instanceof Return_ || ! $statement->expr instanceof Array_) {
                continue;
            }

            foreach ($this->arrayStringKeys($statement->expr) as $column) {
                $this->emitForAllTargetTables($column, $statement, Confidence::HIGH, 'rules()');
            }
        }
    }

    private function emitForAllTargetTables(string $column, Node $node, Confidence $confidence, string $detail): void
    {
        foreach ($this->targets?->tablesForColumn($column) ?? [] as $table) {
            $this->emit(new Usage(
                new ColumnReference($table, $column),
                SurfaceType::CONTROLLER,
                $confidence,
                $this->location($node),
                $detail,
            ));
        }
    }

    /**
     * @return string[]
     */
    private function arrayStringKeys(Array_ $array): array
    {
        $keys = [];

        foreach ($array->items as $item) {
            if ($item?->key instanceof String_) {
                $keys[] = $item->key->value;
            }
        }

        return $keys;
    }

    /**
     * @return string[]
     */
    private function arrayStringValues(Array_ $array): array
    {
        $values = [];

        foreach ($array->items as $item) {
            if ($item?->value instanceof String_) {
                $values[] = $item->value->value;
            }
        }

        return $values;
    }

    private function methodName(MethodCall $call): ?string
    {
        return $call->name instanceof Identifier ? $call->name->toString() : null;
    }
}
