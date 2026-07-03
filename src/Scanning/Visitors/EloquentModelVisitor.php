<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning\Visitors;

use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use SchemaGuard\Scanning\ModelTableMap;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SurfaceType;
use SchemaGuard\ValueObjects\Usage;

final class EloquentModelVisitor extends AbstractUsageVisitor
{
    private const MODE_REGISTRATION = 'registration';
    private const MODE_USAGE = 'usage';

    private const MODEL_BASES = [
        'Illuminate\Database\Eloquent\Model',
        'Illuminate\Database\Eloquent\Relations\Pivot',
        'Illuminate\Foundation\Auth\User',
    ];

    private const ARRAY_VALUE_PROPERTIES = [
        'fillable',
        'guarded',
        'hidden',
        'visible',
        'dates',
    ];

    private const RELATION_METHODS = [
        'belongsTo',
        'belongsToMany',
        'hasManyThrough',
        'hasOneThrough',
        'hasMany',
        'hasOne',
        'morphMany',
        'morphOne',
        'morphTo',
    ];

    /** @var array<int, array{fqcn:string,table:string,structural:array<string, true>}|null> */
    private array $modelStack = [];

    private function __construct(
        private readonly ModelTableMap $modelTableMap,
        private readonly string $mode,
    ) {
    }

    public static function registration(ModelTableMap $modelTableMap): self
    {
        return new self($modelTableMap, self::MODE_REGISTRATION);
    }

    public static function usage(ModelTableMap $modelTableMap): self
    {
        return new self($modelTableMap, self::MODE_USAGE);
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Class_) {
            return $this->enterClass($node);
        }

        if ($this->currentModel() === null) {
            return null;
        }

        if ($node instanceof Property) {
            $this->handleProperty($node);
        }

        if ($node instanceof ClassMethod) {
            $this->handleAccessorMutator($node);
        }

        if ($node instanceof MethodCall) {
            $this->handleRelationship($node);
            $this->handleScopeQuery($node);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Class_ && $this->mode === self::MODE_USAGE) {
            array_pop($this->modelStack);
        }

        return null;
    }

    private function enterClass(Class_ $class): ?int
    {
        if ($this->mode === self::MODE_REGISTRATION) {
            if ($this->isModelClass($class) && ($fqcn = $this->classFqcn($class)) !== null) {
                $this->modelTableMap->register($fqcn, $this->explicitTable($class));
                $this->registerRelations($class, $fqcn);
            }

            return null;
        }

        $fqcn = $this->classFqcn($class);
        $table = $fqcn === null ? null : $this->modelTableMap->tableForModel($fqcn);
        $this->modelStack[] = $fqcn !== null && $table !== null
            ? ['fqcn' => $fqcn, 'table' => $table, 'structural' => $this->structuralColumns($class)]
            : null;

        return null;
    }

    private function handleProperty(Property $property): void
    {
        $model = $this->currentModel();
        $name = $property->props[0]->name->toString();
        $value = $property->props[0]->default;

        if (! $value instanceof Array_) {
            return;
        }

        if (in_array($name, self::ARRAY_VALUE_PROPERTIES, true)) {
            foreach ($this->arrayStringValues($value) as $column) {
                $this->emitModelSchemaUsage($model['table'], $column, $property, '$' . $name);
            }
        }

        if ($name === 'casts') {
            foreach ($this->arrayStringKeys($value) as $column) {
                $this->emitModelSchemaUsage($model['table'], $column, $property, '$casts');
            }
        }
    }

    private function handleAccessorMutator(ClassMethod $method): void
    {
        $model = $this->currentModel();
        $methodName = $method->name->toString();

        if (preg_match('/^(get|set)(.+)Attribute$/', $methodName, $matches) === 1) {
            $this->emitModelSchemaUsage($model['table'], Str::snake($matches[2]), $method, $methodName);

            return;
        }

        if (! $this->returnsAttributeObject($method)) {
            return;
        }

        $column = Str::snake($methodName);
        if (isset($model['structural'][$column])) {
            $this->emitModelSchemaUsage($model['table'], $column, $method, $methodName);
        }
    }

    private function handleRelationship(MethodCall $call): void
    {
        $method = $this->methodName($call);

        if ($method === null || ! in_array($method, self::RELATION_METHODS, true)) {
            return;
        }

        $model = $this->currentModel();

        match ($method) {
            'belongsTo' => $this->handleBelongsTo($call, $model['table'], $method),
            'hasMany', 'hasOne' => $this->handleHasOneOrMany($call, $model['table'], $method),
            'belongsToMany' => $this->handleBelongsToMany($call, $model['table'], $method),
            'hasManyThrough', 'hasOneThrough' => $this->handleThroughRelation($call, $model['table'], $method),
            'morphMany', 'morphOne' => $this->handleMorphOneOrMany($call, $method),
            'morphTo' => $this->handleMorphTo($call, $model['table'], $method),
            default => null,
        };
    }

    private function handleBelongsTo(MethodCall $call, string $currentTable, string $method): void
    {
        $relatedTable = $this->relatedTableFromFirstArg($call);

        $this->emitStringArgRelation($call, 1, $currentTable, $method);

        if ($relatedTable !== null) {
            $this->emitStringArgRelation($call, 2, $relatedTable, $method);
        }
    }

    private function handleHasOneOrMany(MethodCall $call, string $currentTable, string $method): void
    {
        $relatedTable = $this->relatedTableFromFirstArg($call);

        if ($relatedTable !== null) {
            $this->emitStringArgRelation($call, 1, $relatedTable, $method);
        }

        $this->emitStringArgRelation($call, 2, $currentTable, $method);
    }

    private function handleBelongsToMany(MethodCall $call, string $currentTable, string $method): void
    {
        $pivotTable = $this->stringArg($call, 1);
        $relatedTable = $this->relatedTableFromFirstArg($call);

        if ($pivotTable !== null) {
            $this->emitStringArgRelation($call, 2, $pivotTable, $method);
            $this->emitStringArgRelation($call, 3, $pivotTable, $method);
        }

        $this->emitStringArgRelation($call, 4, $currentTable, $method);

        if ($relatedTable !== null) {
            $this->emitStringArgRelation($call, 5, $relatedTable, $method);
        }
    }

    private function handleThroughRelation(MethodCall $call, string $currentTable, string $method): void
    {
        $relatedTable = $this->relatedTableFromFirstArg($call);
        $throughTable = $this->tableFromClassArgument($call, 1);

        if ($throughTable !== null) {
            $this->emitStringArgRelation($call, 2, $throughTable, $method);
        }

        if ($relatedTable !== null) {
            $this->emitStringArgRelation($call, 3, $relatedTable, $method);
        }

        $this->emitStringArgRelation($call, 4, $currentTable, $method);

        if ($throughTable !== null) {
            $this->emitStringArgRelation($call, 5, $throughTable, $method);
        }
    }

    private function handleMorphOneOrMany(MethodCall $call, string $method): void
    {
        $relatedTable = $this->relatedTableFromFirstArg($call);
        $name = $this->stringArg($call, 1);

        if ($relatedTable === null || $name === null) {
            return;
        }

        $this->emitRelationUsage($relatedTable, $name . '_type', $call, $method);
        $this->emitRelationUsage($relatedTable, $name . '_id', $call, $method);
    }

    private function handleMorphTo(MethodCall $call, string $currentTable, string $method): void
    {
        $name = $this->stringArg($call, 0);

        if ($name === null) {
            $enclosing = $this->enclosingMethod($call);
            $name = $enclosing?->name->toString();
        }

        if ($name === null || $name === '') {
            return;
        }

        $this->emitRelationUsage($currentTable, $name . '_type', $call, $method);
        $this->emitRelationUsage($currentTable, $name . '_id', $call, $method);
    }

    private function handleScopeQuery(MethodCall $call): void
    {
        $method = $this->enclosingMethod($call);

        if ($method === null || ! str_starts_with($method->name->toString(), 'scope')) {
            return;
        }

        if (! ($call->args[0]->value ?? null) instanceof String_) {
            return;
        }

        $model = $this->currentModel();
        $column = $call->args[0]->value->value;

        if (! $this->targets?->hasColumn($model['table'], $column)) {
            return;
        }

        $this->emit(new Usage(
            new ColumnReference($model['table'], $column),
            SurfaceType::ELOQUENT_QUERY,
            Confidence::DEFINITIVE,
            $this->location($call),
            $method->name->toString(),
        ));
    }

    private function emitModelSchemaUsage(string $table, string $column, Node $node, string $detail): void
    {
        if (! $this->targets?->hasColumn($table, $column)) {
            return;
        }

        $this->emit(new Usage(
            new ColumnReference($table, $column),
            SurfaceType::MODEL_SCHEMA,
            Confidence::DEFINITIVE,
            $this->location($node),
            $detail,
        ));
    }

    private function emitRelationUsage(string $table, string $column, Node $node, string $detail): void
    {
        if (! $this->targets?->hasColumn($table, $column)) {
            return;
        }

        $this->emit(new Usage(
            new ColumnReference($table, $column),
            SurfaceType::RELATION,
            Confidence::DEFINITIVE,
            $this->location($node),
            $detail,
        ));
    }

    private function isModelClass(Class_ $class): bool
    {
        if ($class->extends instanceof Name && in_array($this->resolveName($class->extends), self::MODEL_BASES, true)) {
            return true;
        }

        if ($class->extends instanceof Name && $this->modelTableMap->hasModel($this->resolveName($class->extends))) {
            return true;
        }

        return $this->hasProperty($class, 'fillable')
            || $this->hasProperty($class, 'casts')
            || ($this->explicitTable($class) !== null && $this->hasRelationshipMethod($class));
    }

    private function explicitTable(Class_ $class): ?string
    {
        foreach ($class->getProperties() as $property) {
            if ($property->props[0]->name->toString() === 'table' && $property->props[0]->default instanceof String_) {
                return $property->props[0]->default->value;
            }
        }

        return null;
    }

    private function hasProperty(Class_ $class, string $name): bool
    {
        foreach ($class->getProperties() as $property) {
            if ($property->props[0]->name->toString() === $name) {
                return true;
            }
        }

        return false;
    }

    private function hasRelationshipMethod(Class_ $class): bool
    {
        foreach ($class->getMethods() as $method) {
            foreach ($method->stmts ?? [] as $statement) {
                if (! $statement instanceof \PhpParser\Node\Stmt\Return_ || ! $statement->expr instanceof MethodCall) {
                    continue;
                }

                if (in_array($this->methodName($statement->expr), self::RELATION_METHODS, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function structuralColumns(Class_ $class): array
    {
        $columns = [];

        foreach ($class->getProperties() as $property) {
            $name = $property->props[0]->name->toString();
            $value = $property->props[0]->default;

            if (! $value instanceof Array_) {
                continue;
            }

            if (in_array($name, self::ARRAY_VALUE_PROPERTIES, true)) {
                foreach ($this->arrayStringValues($value) as $column) {
                    $columns[$column] = true;
                }
            }

            if ($name === 'casts') {
                foreach ($this->arrayStringKeys($value) as $column) {
                    $columns[$column] = true;
                }
            }
        }

        return $columns;
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

    private function returnsAttributeObject(ClassMethod $method): bool
    {
        if ($method->returnType instanceof Name && $this->resolveName($method->returnType) === 'Illuminate\Database\Eloquent\Casts\Attribute') {
            return true;
        }

        return false;
    }

    private function registerRelations(Class_ $class, string $fqcn): void
    {
        foreach ($class->getMethods() as $method) {
            foreach ($method->stmts ?? [] as $statement) {
                if (! $statement instanceof \PhpParser\Node\Stmt\Return_) {
                    continue;
                }

                $expr = $statement->expr;
                if (! $expr instanceof MethodCall || ! in_array($this->methodName($expr), self::RELATION_METHODS, true)) {
                    continue;
                }

                $related = $this->relatedModelFromFirstArg($expr);
                if ($related !== null) {
                    $this->modelTableMap->registerRelation($fqcn, $method->name->toString(), $related);
                }
            }
        }
    }

    private function relatedTableFromFirstArg(MethodCall $call): ?string
    {
        $related = $this->relatedModelFromFirstArg($call);

        return $related === null ? null : $this->modelTableMap->tableForModel($related);
    }

    private function tableFromClassArgument(MethodCall $call, int $position): ?string
    {
        $expr = $call->args[$position]->value ?? null;

        if (! $expr instanceof ClassConstFetch || ! $expr->class instanceof Name) {
            return null;
        }

        return $this->modelTableMap->tableForModel($this->resolveName($expr->class));
    }

    private function relatedModelFromFirstArg(MethodCall $call): ?string
    {
        $expr = $call->args[0]->value ?? null;

        if (! $expr instanceof ClassConstFetch || ! $expr->class instanceof Name) {
            return null;
        }

        return $this->resolveName($expr->class);
    }

    private function emitStringArgRelation(MethodCall $call, int $position, string $table, string $detail): void
    {
        $column = $this->stringArg($call, $position);

        if ($column !== null) {
            $this->emitRelationUsage($table, $column, $call, $detail);
        }
    }

    private function stringArg(MethodCall $call, int $position): ?string
    {
        $value = $call->args[$position]->value ?? null;

        return $value instanceof String_ ? $value->value : null;
    }

    /**
     * @return array{fqcn:string,table:string,structural:array<string, true>}|null
     */
    private function currentModel(): ?array
    {
        $model = end($this->modelStack);

        return is_array($model) ? $model : null;
    }

    private function methodName(MethodCall $call): ?string
    {
        return $call->name instanceof Node\Identifier ? $call->name->toString() : null;
    }
}
