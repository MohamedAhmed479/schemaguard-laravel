<?php

declare(strict_types=1);

namespace SchemaGuard\Migrations\Visitors;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use SchemaGuard\ValueObjects\ChangeType;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\SourceLocation;
use SchemaGuard\ValueObjects\TableReference;

final class SchemaCallVisitor extends NodeVisitorAbstract
{
    private const COLUMN_TYPE_METHODS = [
        'bigInteger',
        'binary',
        'boolean',
        'char',
        'date',
        'dateTime',
        'dateTimeTz',
        'decimal',
        'double',
        'float',
        'foreignId',
        'integer',
        'json',
        'jsonb',
        'longText',
        'mediumInteger',
        'mediumText',
        'smallInteger',
        'string',
        'text',
        'time',
        'timestamp',
        'timestampTz',
        'tinyInteger',
        'unsignedBigInteger',
        'unsignedInteger',
        'uuid',
        'ulid',
    ];

    /** @var SchemaChangeEvent[] */
    private array $events = [];

    /** @var array<string, true> */
    private array $addedColumns = [];

    /** @var array<int, array{table:?string,blueprint:?string}> */
    private array $closureContexts = [];

    /** @var array<int, array{table:?string,blueprint:?string}> */
    private array $contextStack = [];

    private bool $insideUp = false;

    public function __construct(private readonly string $filePath)
    {
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof ClassMethod && $node->name->toString() === 'up') {
            $this->insideUp = true;

            return null;
        }

        if (! $this->insideUp) {
            return null;
        }

        if ($node instanceof Closure && isset($this->closureContexts[spl_object_id($node)])) {
            $this->contextStack[] = $this->closureContexts[spl_object_id($node)];

            return null;
        }

        if ($node instanceof StaticCall && $this->isSchemaFacadeCall($node)) {
            $this->handleSchemaCall($node);

            return null;
        }

        if ($node instanceof MethodCall && $this->isBlueprintCall($node)) {
            $this->handleBlueprintCall($node);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Closure && isset($this->closureContexts[spl_object_id($node)])) {
            array_pop($this->contextStack);
            unset($this->closureContexts[spl_object_id($node)]);

            return null;
        }

        if ($node instanceof ClassMethod && $node->name->toString() === 'up') {
            $this->insideUp = false;
            $this->contextStack = [];
            $this->closureContexts = [];
        }

        return null;
    }

    /**
     * @return SchemaChangeEvent[]
     */
    public function events(): array
    {
        return array_map(function (SchemaChangeEvent $event): SchemaChangeEvent {
            if (
                $event->type === ChangeType::COLUMN_DROPPED
                && $event->column !== null
                && isset($this->addedColumns[$event->column->id()])
            ) {
                return $event->neutralized(sprintf(
                    'Drop of %s was neutralized by a same-migration re-add.',
                    "{$event->column->table}.{$event->column->column}",
                ));
            }

            return $event;
        }, $this->events);
    }

    private function handleSchemaCall(StaticCall $node): void
    {
        $method = $this->methodName($node);

        if ($method === null) {
            return;
        }

        if (in_array($method, ['table', 'create'], true)) {
            $closure = $this->schemaClosure($node->args[1] ?? null);

            if ($closure !== null) {
                $this->closureContexts[spl_object_id($closure)] = [
                    'table' => $this->literalString($node->args[0]->value ?? null),
                    'blueprint' => $this->blueprintVariable($closure),
                ];
            }

            return;
        }

        if (! in_array($method, ['drop', 'dropIfExists'], true)) {
            return;
        }

        $location = SourceLocation::fromNode($this->filePath, $node);
        $table = $this->literalString($node->args[0]->value ?? null);

        if ($table === null) {
            $this->events[] = SchemaChangeEvent::indeterminate(
                ChangeType::TABLE_DROPPED,
                null,
                'dynamic table name',
                $location,
            );

            return;
        }

        $this->events[] = SchemaChangeEvent::tableDropped(new TableReference($table), $location);
    }

    private function handleBlueprintCall(MethodCall $node): void
    {
        $method = $this->methodName($node);

        if ($method === null) {
            return;
        }

        if (in_array($method, ['dropColumn', 'dropColumns'], true)) {
            $this->handleDropColumn($node);

            return;
        }

        if ($method === 'renameColumn') {
            $this->handleRenameColumn($node);

            return;
        }

        if (in_array($method, self::COLUMN_TYPE_METHODS, true)) {
            if ($this->chainHasChangeModifier($node)) {
                $this->handleColumnTypeChanged($node, $method);

                return;
            }

            $this->handleColumnAdded($node);
        }
    }

    private function handleDropColumn(MethodCall $node): void
    {
        $table = $this->currentTable();
        $tableReference = $table === null ? null : new TableReference($table);
        $location = SourceLocation::fromNode($this->filePath, $node);

        foreach ($this->columnList($node->args[0]->value ?? null) as $column) {
            if ($column === null || $table === null) {
                $this->events[] = SchemaChangeEvent::indeterminate(
                    ChangeType::COLUMN_DROPPED,
                    $tableReference,
                    $table === null ? 'dynamic table name' : 'dynamic column name',
                    $location,
                );

                continue;
            }

            $this->events[] = SchemaChangeEvent::columnDropped(new ColumnReference($table, $column), $location);
        }
    }

    private function handleRenameColumn(MethodCall $node): void
    {
        $table = $this->currentTable();
        $tableReference = $table === null ? null : new TableReference($table);
        $from = $this->literalString($node->args[0]->value ?? null);
        $to = $this->literalString($node->args[1]->value ?? null);
        $location = SourceLocation::fromNode($this->filePath, $node);

        if ($from === null || $table === null) {
            $this->events[] = SchemaChangeEvent::indeterminate(
                ChangeType::COLUMN_RENAMED,
                $tableReference,
                $table === null ? 'dynamic table name' : 'dynamic old column name',
                $location,
            );

            return;
        }

        $this->events[] = SchemaChangeEvent::columnRenamed(new ColumnReference($table, $from), $to, $location);
    }

    private function handleColumnTypeChanged(MethodCall $node, string $newType): void
    {
        $table = $this->currentTable();
        $tableReference = $table === null ? null : new TableReference($table);
        $column = $this->literalString($node->args[0]->value ?? null);
        $location = SourceLocation::fromNode($this->filePath, $node);

        if ($column === null || $table === null) {
            $this->events[] = SchemaChangeEvent::indeterminate(
                ChangeType::COLUMN_TYPE_CHANGED,
                $tableReference,
                $table === null ? 'dynamic table name' : 'dynamic column name',
                $location,
            );

            return;
        }

        $this->events[] = SchemaChangeEvent::columnTypeChanged(new ColumnReference($table, $column), $newType, $location);
    }

    private function handleColumnAdded(MethodCall $node): void
    {
        $table = $this->currentTable();
        $column = $this->literalString($node->args[0]->value ?? null);

        if ($table === null || $column === null) {
            return;
        }

        $this->addedColumns[(new ColumnReference($table, $column))->id()] = true;
    }

    private function isSchemaFacadeCall(StaticCall $node): bool
    {
        if (! $node->class instanceof Name) {
            return false;
        }

        $resolvedName = $node->class->getAttribute('resolvedName');
        $class = ltrim(($resolvedName instanceof Name ? $resolvedName : $node->class)->toString(), '\\');

        return in_array($class, ['Schema', 'Illuminate\Support\Facades\Schema'], true);
    }

    private function isBlueprintCall(MethodCall $node): bool
    {
        $blueprint = $this->currentBlueprintVariable();

        return $blueprint !== null && $this->rootVariableName($node) === $blueprint;
    }

    private function schemaClosure(?Arg $arg): ?Closure
    {
        return $arg?->value instanceof Closure ? $arg->value : null;
    }

    private function blueprintVariable(Closure $closure): ?string
    {
        $variable = $closure->params[0]->var ?? null;

        return $variable instanceof Variable && is_string($variable->name) ? $variable->name : null;
    }

    private function currentTable(): ?string
    {
        $context = end($this->contextStack);

        return is_array($context) ? $context['table'] : null;
    }

    private function currentBlueprintVariable(): ?string
    {
        $context = end($this->contextStack);

        return is_array($context) ? $context['blueprint'] : null;
    }

    private function rootVariableName(MethodCall $node): ?string
    {
        $cursor = $node->var;

        while ($cursor instanceof MethodCall) {
            $cursor = $cursor->var;
        }

        if (! $cursor instanceof Variable || ! is_string($cursor->name)) {
            return null;
        }

        return $cursor->name;
    }

    private function chainHasChangeModifier(MethodCall $node): bool
    {
        $cursor = $node;

        while (($parent = $cursor->getAttribute('parent')) instanceof MethodCall && $parent->var === $cursor) {
            if ($this->methodName($parent) === 'change') {
                return true;
            }

            $cursor = $parent;
        }

        return false;
    }

    /**
     * @return array<int, string|null>
     */
    private function columnList(?Expr $expr): array
    {
        $literal = $this->literalString($expr);

        if ($literal !== null) {
            return [$literal];
        }

        if (! $expr instanceof Array_) {
            return [null];
        }

        $columns = [];
        $hasDynamicItem = false;

        foreach ($expr->items as $item) {
            if ($item === null) {
                continue;
            }

            $column = $this->literalString($item->value);

            if ($column === null) {
                $hasDynamicItem = true;
                continue;
            }

            $columns[] = $column;
        }

        if ($hasDynamicItem) {
            $columns[] = null;
        }

        return $columns === [] ? [null] : $columns;
    }

    private function literalString(?Expr $expr): ?string
    {
        return $expr instanceof String_ ? $expr->value : null;
    }

    private function methodName(MethodCall|StaticCall $node): ?string
    {
        return $node->name instanceof Node\Identifier ? $node->name->toString() : null;
    }
}
