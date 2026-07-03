<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning\Visitors;

use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use SchemaGuard\Scanning\ParsedFile;
use SchemaGuard\ValueObjects\RouteBinding;
use SchemaGuard\ValueObjects\SourceLocation;

final class RouteVisitor extends NodeVisitorAbstract
{
    private ?ParsedFile $file = null;

    /** @var RouteBinding[] */
    private array $bindings = [];

    public function reset(ParsedFile $file): void
    {
        $this->file = $file;
        $this->bindings = [];
    }

    /**
     * @return RouteBinding[]
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    public function enterNode(Node $node)
    {
        if (! $node instanceof StaticCall || ! $this->isRouteFacadeCall($node)) {
            return null;
        }

        $method = $this->methodName($node);

        if ($method === null) {
            return null;
        }

        if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
            $this->handleActionRoute($node, $method);

            return null;
        }

        if (in_array($method, ['apiResource', 'resource'], true)) {
            $this->handleResourceRoute($node, $method);
        }

        return null;
    }

    private function handleActionRoute(StaticCall $node, string $verb): void
    {
        $uri = $this->literalString($node->args[0]->value ?? null);
        $action = $this->controllerAction($node->args[1] ?? null);

        if ($uri === null || $action === null) {
            return;
        }

        $this->bindings[] = new RouteBinding(
            $verb,
            $uri,
            $action['controller'],
            $action['method'],
            $this->location($node),
        );
    }

    private function handleResourceRoute(StaticCall $node, string $routeMethod): void
    {
        $uri = $this->literalString($node->args[0]->value ?? null);
        $controller = $this->controllerClass($node->args[1] ?? null);

        if ($uri === null || $controller === null) {
            return;
        }

        foreach ($this->resourceActions($uri, $routeMethod === 'apiResource') as $action) {
            $this->bindings[] = new RouteBinding(
                $action['verb'],
                $action['uri'],
                $controller,
                $action['method'],
                $this->location($node),
            );
        }
    }

    /**
     * @return array{controller:string,method:string}|null
     */
    private function controllerAction(?Arg $arg): ?array
    {
        $expr = $arg?->value;

        if ($expr instanceof Array_) {
            $controller = $this->controllerClassFromArray($expr);
            $method = $expr->items[1]->value ?? null;

            if ($controller !== null && $method instanceof String_) {
                return ['controller' => $controller, 'method' => $method->value];
            }
        }

        if ($expr instanceof ClassConstFetch) {
            $controller = $this->controllerClassFromClassConst($expr);

            return $controller === null ? null : ['controller' => $controller, 'method' => '__invoke'];
        }

        if ($expr instanceof String_ && str_contains($expr->value, '@')) {
            [$controller, $method] = explode('@', $expr->value, 2);

            return [
                'controller' => ltrim($controller, '\\'),
                'method' => $method,
            ];
        }

        return null;
    }

    private function controllerClass(?Arg $arg): ?string
    {
        $expr = $arg?->value;

        if ($expr instanceof ClassConstFetch) {
            return $this->controllerClassFromClassConst($expr);
        }

        if ($expr instanceof String_) {
            return ltrim($expr->value, '\\');
        }

        return null;
    }

    private function controllerClassFromArray(Array_ $array): ?string
    {
        $class = $array->items[0]->value ?? null;

        return $class instanceof ClassConstFetch ? $this->controllerClassFromClassConst($class) : null;
    }

    private function controllerClassFromClassConst(ClassConstFetch $fetch): ?string
    {
        if (! $fetch->class instanceof Name || ! $fetch->name instanceof Identifier || $fetch->name->toString() !== 'class') {
            return null;
        }

        return $this->resolveName($fetch->class);
    }

    /**
     * @return array<int, array{verb:string,uri:string,method:string}>
     */
    private function resourceActions(string $uri, bool $apiOnly): array
    {
        $baseUri = $this->normalizeUri($uri);
        $memberUri = $baseUri . '/{' . $this->resourceParameter($baseUri) . '}';
        $actions = [
            ['verb' => 'GET', 'uri' => $baseUri, 'method' => 'index'],
            ['verb' => 'POST', 'uri' => $baseUri, 'method' => 'store'],
            ['verb' => 'GET', 'uri' => $memberUri, 'method' => 'show'],
            ['verb' => 'PUT', 'uri' => $memberUri, 'method' => 'update'],
            ['verb' => 'PATCH', 'uri' => $memberUri, 'method' => 'update'],
            ['verb' => 'DELETE', 'uri' => $memberUri, 'method' => 'destroy'],
        ];

        if ($apiOnly) {
            return $actions;
        }

        return [
            ['verb' => 'GET', 'uri' => $baseUri . '/create', 'method' => 'create'],
            ...$actions,
            ['verb' => 'GET', 'uri' => $memberUri . '/edit', 'method' => 'edit'],
        ];
    }

    private function resourceParameter(string $uri): string
    {
        $segments = array_values(array_filter(explode('/', trim($uri, '/'))));
        $last = $segments === [] ? 'resource' : (string) end($segments);

        return Str::singular(str_replace(['-', '.'], '_', $last));
    }

    private function isRouteFacadeCall(StaticCall $node): bool
    {
        if (! $node->class instanceof Name) {
            return false;
        }

        return in_array($this->resolveName($node->class), ['Route', 'Illuminate\Support\Facades\Route'], true);
    }

    private function methodName(StaticCall $node): ?string
    {
        return $node->name instanceof Identifier ? $node->name->toString() : null;
    }

    private function literalString(mixed $expr): ?string
    {
        return $expr instanceof String_ ? $expr->value : null;
    }

    private function location(Node $node): SourceLocation
    {
        return SourceLocation::fromNode($this->file?->path ?? '', $node);
    }

    private function resolveName(Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        return ltrim(($resolved instanceof Name ? $resolved : $name)->toString(), '\\');
    }

    private function normalizeUri(string $uri): string
    {
        $uri = '/' . ltrim($uri, '/');

        return $uri === '/' ? $uri : rtrim($uri, '/');
    }
}
