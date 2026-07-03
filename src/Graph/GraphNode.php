<?php

declare(strict_types=1);

namespace SchemaGuard\Graph;

use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\SourceLocation;
use SchemaGuard\ValueObjects\TableReference;

final readonly class GraphNode
{
    public function __construct(
        public string $id,
        public NodeType $type,
        public string $label,
        public ?SourceLocation $location = null,
    ) {
    }

    public static function column(ColumnReference $column, ?SourceLocation $location = null): self
    {
        return new self($column->id(), NodeType::COLUMN, "{$column->table}.{$column->column}", $location);
    }

    public static function table(TableReference $table, ?SourceLocation $location = null): self
    {
        return new self($table->id(), NodeType::TABLE, $table->table, $location);
    }

    public static function model(string $fqcn, ?SourceLocation $location = null): self
    {
        return new self('model:' . ltrim($fqcn, '\\'), NodeType::MODEL, ltrim($fqcn, '\\'), $location);
    }

    public static function resource(string $fqcn, ?SourceLocation $location = null): self
    {
        return new self('resource:' . ltrim($fqcn, '\\'), NodeType::RESOURCE, ltrim($fqcn, '\\'), $location);
    }

    public static function controller(string $fqcn, ?SourceLocation $location = null): self
    {
        return new self('controller:' . ltrim($fqcn, '\\'), NodeType::CONTROLLER, class_basename($fqcn), $location);
    }

    public static function action(string $controllerFqcn, string $method, ?SourceLocation $location = null): self
    {
        $controllerFqcn = ltrim($controllerFqcn, '\\');

        return new self(
            "action:{$controllerFqcn}@{$method}",
            NodeType::CONTROLLER_ACTION,
            class_basename($controllerFqcn) . "@{$method}",
            $location,
        );
    }

    public static function route(string $verb, string $uri, ?SourceLocation $location = null): self
    {
        $verb = strtoupper($verb);
        $uri = self::normalizeUri($uri);

        return new self("route:{$verb}:{$uri}", NodeType::ROUTE, "{$verb} {$uri}", $location);
    }

    private static function normalizeUri(string $uri): string
    {
        $uri = '/' . ltrim($uri, '/');

        return $uri === '/' ? $uri : rtrim($uri, '/');
    }
}
