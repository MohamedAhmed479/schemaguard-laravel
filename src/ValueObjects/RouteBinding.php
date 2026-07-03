<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

final readonly class RouteBinding
{
    public string $verb;

    public string $uri;

    public string $controllerFqcn;

    public string $method;

    public function __construct(
        string $verb,
        string $uri,
        string $controllerFqcn,
        string $method,
        public SourceLocation $location,
    ) {
        $this->verb = strtoupper($verb);
        $this->uri = self::normalizeUri($uri);
        $this->controllerFqcn = ltrim($controllerFqcn, '\\');
        $this->method = $method;
    }

    public function routeId(): string
    {
        return "route:{$this->verb}:{$this->uri}";
    }

    public function actionId(): string
    {
        return "action:{$this->controllerFqcn}@{$this->method}";
    }

    public function label(): string
    {
        return "{$this->verb} {$this->uri}";
    }

    private static function normalizeUri(string $uri): string
    {
        $uri = '/' . ltrim($uri, '/');

        return $uri === '/' ? $uri : rtrim($uri, '/');
    }
}
