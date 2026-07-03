<?php

declare(strict_types=1);

namespace SchemaGuard\Migrations\Visitors;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
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
        'bigIncrements',
        'bigInteger',
        'binary',
        'boolean',
        'char',
        'date',
        'dateTime',
        'dateTimeTz',
        'decimal',
        'double',
        'enum',
        'float',
        'foreignId',
        'geometry',
        'id',
        'increments',
        'integer',
        'ipAddress',
        'json',
        'jsonb',
        'longText',
        'macAddress',
        'mediumIncrements',
        'mediumInteger',
        'mediumText',
        'morphs',
        'nullableMorphs',
        'nullableTimestamps',
        'rememberToken',
        'set',
        'smallIncrements',
        'smallInteger',
        'softDeletes',
        'softDeletesTz',
        'string',
        'text',
        'time',
        'timeTz',
        'timestamp',
        'timestamps',
        'timestampsTz',
        'tinyIncrements',
        'tinyInteger',
        'unsignedBigInteger',
        'unsignedDecimal',
        'unsignedInteger',
        'unsignedMediumInteger',
        'unsignedSmallInteger',
        'unsignedTinyInteger',
        'uuid',
        'ulid',
        'year',
    ];

    /** @var array<int, SchemaChangeEvent> */
    private array $events = [];

    private ?string $currentTable = null;

    private ?string $blueprintVar = null;

    private bool $insideUp = false;

    public function __construct(private readonly string $filePath)
    {
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof ClassMethod && $node->name->toString() === 'up') {
            $this->insideUp = true;
        }

        if (! $this->insideUp) {
            return null;
        }

        if ($node instanceof Expr\StaticCall && $this->isSchemaFacade($node)) {
            $this->handleSchemaCall($node);
        }

        if ($node instanceof Expr\MethodCall && $this->isBlueprintCall($node)) {
            $this->handleBlueprintCall($node);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof ClassMethod && $node->name->toString() === 'up') {
            $this->insideUp = false;
            $this->currentTable = null;
            $this->blueprintVar = null;
        }

        if ($node instanceof Expr\Closure && $node->getAttribute('schemaguard_schema_closure') === true) {
            $this->currentTable = $node->getAttribute('schemaguard_previous_table');
            $this->blueprintVar = $node->getAttribute('schemaguard_previous_blueprint_var');
        }

        return null;
    }

    /**
     * @return array<int, SchemaChangeEvent>
     */
    public function events(): array
    {
        return $this->events;
    }

    private function handleSchemaCall(Expr\StaticCall $call): void
    {
        $method = $this->nodeName($call->name);
        if ($method === null) {
            return;
        }

        if (in_array($method, ['table', 'create'], true)) {
            $this->enterSchemaTableContext($call);

            return;
        }

        if (! in_array($method, ['drop', 'dropIfExists'], true)) {
            return;
        }

        $table = $this->literalString($call->args[0]->value ?? null);
        $location = SourceLocation::fromNode($this->filePath, $call);

        if ($table === null) {
            $this->events[] = SchemaChangeEvent::indeterminate(
                ChangeType::TABLE_DROPPED,
                null,
                'dynamic table name',
                $location,
            );

            return;
        }

        $this->events[] = SchemaChangeEvent::tableDropped(
            new TableReference($table),
            $location,
        );
    }

    private function enterSchemaTableContext(Expr\StaticCall $call): void
    {
        $table = $this->literalString($call->args[0]->value ?? null);
        $closure = $call->args[1]->value ?? null;

        if ($closure instanceof Expr\Closure) {
            $closure->setAttribute('schemaguard_schema_closure', true);
            $closure->setAttribute('schemaguard_previous_table', $this->currentTable);
            $closure->setAttribute('schemaguard_previous_blueprint_var', $this->blueprintVar);
        }

        $this->currentTable = $table;
        $this->blueprintVar = $this->resolveBlueprintVar($call->args[1] ?? null);
    }

    private function handleBlueprintCall(Expr\MethodCall $call): void
    {
        $method = $this->nodeName($call->name);
        if ($method === null) {
            return;
        }

        match (true) {
            in_array($method, ['dropColumn', 'dropColumns'], true) => $this->handleDropColumn($call),
            $method === 'renameColumn' => $this->handleRenameColumn($call),
            in_array($method, self::COLUMN_TYPE_METHODS, true) => $this->handleColumnTypeChange($call, $method),
            default => null,
        };
    }

    private function handleDropColumn(Expr\MethodCall $call): void
    {
        $location = SourceLocation::fromNode($this->filePath, $call);
        $table = $this->currentTableReference();

        foreach ($this->extractColumns($call->args[0]->value ?? null) as $column) {
            if ($column === null || $table === null) {
                $this->events[] = SchemaChangeEvent::indeterminate(
                    ChangeType::COLUMN_DROPPED,
                    $table,
                    $table === null ? 'dynamic table name' : 'dynamic column name',
                    $location,
                );

                continue;
            }

            $this->events[] = SchemaChangeEvent::columnDropped(
                new ColumnReference($table->table, $column),
                $location,
            );
        }
    }

    private function handleRenameColumn(Expr\MethodCall $call): void
    {
        $location = SourceLocation::fromNode($this->filePath, $call);
        $table = $this->currentTableReference();
        $from = $this->literalString($call->args[0]->value ?? null);
        $to = $this->literalString($call->args[1]->value ?? null);

        if ($from === null || $table === null) {
            $this->events[] = SchemaChangeEvent::indeterminate(
                ChangeType::COLUMN_RENAMED,
                $table,
                $table === null ? 'dynamic table name' : 'dynamic old column name',
                $location,
            );

            return;
        }

        $this->events[] = SchemaChangeEvent::columnRenamed(
            new ColumnReference($table->table, $from),
            $to,
            $location,
        );
    }

    private function handleColumnTypeChange(Expr\MethodCall $call, string $newType): void
    {
        if (! $this->chainHasChangeModifier($call)) {
            return;
        }

        $location = SourceLocation::fromNode($this->filePath, $call);
        $table = $this->currentTableReference();
        $column = $this->literalString($call->args[0]->value ?? null);

        if ($column === null || $table === null) {
            $this->events[] = SchemaChangeEvent::indeterminate(
                ChangeType::COLUMN_TYPE_CHANGED,
                $table,
                $table === null ? 'dynamic table name' : 'dynamic column name',
                $location,
            );

            return;
        }

        $this->events[] = SchemaChangeEvent::columnTypeChanged(
            new ColumnReference($table->table, $column),
            $newType,
            $location,
        );
    }

    private function isSchemaFacade(Expr\StaticCall $call): bool
    {
        if (! $call->class instanceof Name) {
            return false;
        }

        $resolved = $call->class->getAttribute('resolvedName');
        $candidates = [
            ltrim($call->class->toString(), '\\'),
        ];

        if ($resolved instanceof Name) {
            $candidates[] = ltrim($resolved->toString(), '\\');
        }

        return (bool) array_intersect($candidates, [
            'Schema',
            'Illuminate\Support\Facades\Schema',
        ]);
    }

    private function isBlueprintCall(Expr\MethodCall $call): bool
    {
        return $this->blueprintVar !== null && $this->receiverRootVariable($call) === $this->blueprintVar;
    }

    private function receiverRootVariable(Expr $expr): ?string
    {
        $cursor = $expr instanceof Expr\MethodCall ? $expr->var : $expr;

        while ($cursor instanceof Expr\MethodCall || $cursor instanceof Expr\PropertyFetch) {
            $cursor = $cursor->var;
        }

        if ($cursor instanceof Expr\Variable && is_string($cursor->name)) {
            return $cursor->name;
        }

        return null;
    }

    private function resolveBlueprintVar(?Arg $arg): ?string
    {
        $closure = $arg?->value;

        if (! $closure instanceof Expr\Closure || ! isset($closure->params[0])) {
            return null;
        }

        $variable = $closure->params[0]->var;

        return $variable instanceof Expr\Variable && is_string($variable->name)
            ? $variable->name
            : null;
    }

    private function literalString(?Node $node): ?string
    {
        return $node instanceof String_ ? $node->value : null;
    }

    /**
     * @return array<int, string|null>
     */
    private function extractColumns(?Node $node): array
    {
        if ($node instanceof String_) {
            return [$node->value];
        }

        if ($node instanceof Expr\Array_) {
            $columns = [];

            foreach ($node->items as $item) {
                if ($item === null) {
                    continue;
                }

                $columns[] = $item->value instanceof String_ ? $item->value->value : null;
            }

            return $columns === [] ? [null] : $columns;
        }

        return [null];
    }

    private function chainHasChangeModifier(Expr\MethodCall $call): bool
    {
        $cursor = $call;

        while (true) {
            $parent = $cursor->getAttribute('parent');

            if (! $parent instanceof Expr\MethodCall || $parent->var !== $cursor) {
                return false;
            }

            $cursor = $parent;

            if ($this->nodeName($cursor->name) === 'change') {
                return true;
            }
        }
    }

    private function currentTableReference(): ?TableReference
    {
        return $this->currentTable === null ? null : new TableReference($this->currentTable);
    }

    private function nodeName(Node|Identifier|string $name): ?string
    {
        if ($name instanceof Identifier || $name instanceof Name) {
            return $name->toString();
        }

        return is_string($name) ? $name : null;
    }
}
