<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use Illuminate\Filesystem\Filesystem;
use PhpParser\NodeTraverser;
use SchemaGuard\Scanning\CodebaseIndexer;
use SchemaGuard\Scanning\ParsedFile;
use SchemaGuard\Scanning\Visitors\RouteVisitor;
use SchemaGuard\Tests\TestCase;

final class RouteVisitorTest extends TestCase
{
    public function test_it_extracts_controller_action_routes(): void
    {
        $bindings = $this->routeBindings();

        $this->assertTrue($this->hasBinding($bindings, 'GET', '/api/users/{user}', 'App\Http\Controllers\UserController', 'show'));
        $this->assertTrue($this->hasBinding($bindings, 'POST', '/api/users', 'App\Http\Controllers\UserController', 'store'));
    }

    public function test_it_expands_api_resource_routes(): void
    {
        $bindings = $this->routeBindings();

        $this->assertTrue($this->hasBinding($bindings, 'GET', '/api/accounts', 'App\Http\Controllers\UserController', 'index'));
        $this->assertTrue($this->hasBinding($bindings, 'POST', '/api/accounts', 'App\Http\Controllers\UserController', 'store'));
        $this->assertTrue($this->hasBinding($bindings, 'GET', '/api/accounts/{account}', 'App\Http\Controllers\UserController', 'show'));
        $this->assertTrue($this->hasBinding($bindings, 'PUT', '/api/accounts/{account}', 'App\Http\Controllers\UserController', 'update'));
        $this->assertTrue($this->hasBinding($bindings, 'PATCH', '/api/accounts/{account}', 'App\Http\Controllers\UserController', 'update'));
        $this->assertTrue($this->hasBinding($bindings, 'DELETE', '/api/accounts/{account}', 'App\Http\Controllers\UserController', 'destroy'));
    }

    public function test_it_expands_web_resource_routes(): void
    {
        $bindings = $this->routeBindings();

        $this->assertTrue($this->hasBinding($bindings, 'GET', '/web/users', 'App\Http\Controllers\UserController', 'index'));
        $this->assertTrue($this->hasBinding($bindings, 'GET', '/web/users/create', 'App\Http\Controllers\UserController', 'create'));
        $this->assertTrue($this->hasBinding($bindings, 'POST', '/web/users', 'App\Http\Controllers\UserController', 'store'));
        $this->assertTrue($this->hasBinding($bindings, 'GET', '/web/users/{user}', 'App\Http\Controllers\UserController', 'show'));
        $this->assertTrue($this->hasBinding($bindings, 'GET', '/web/users/{user}/edit', 'App\Http\Controllers\UserController', 'edit'));
        $this->assertTrue($this->hasBinding($bindings, 'PUT', '/web/users/{user}', 'App\Http\Controllers\UserController', 'update'));
        $this->assertTrue($this->hasBinding($bindings, 'PATCH', '/web/users/{user}', 'App\Http\Controllers\UserController', 'update'));
        $this->assertTrue($this->hasBinding($bindings, 'DELETE', '/web/users/{user}', 'App\Http\Controllers\UserController', 'destroy'));
    }

    public function test_it_ignores_unsupported_dynamic_route_input(): void
    {
        $bindings = $this->routeBindings();

        foreach ($bindings as $binding) {
            $this->assertNotSame('/api/dynamic', $binding->uri);
        }
    }

    /**
     * @return array<int, \SchemaGuard\ValueObjects\RouteBinding>
     */
    private function routeBindings(): array
    {
        $file = $this->indexedRouteFile();
        $visitor = new RouteVisitor();
        $visitor->reset($file);
        (new NodeTraverser($visitor))->traverse($file->ast ?? []);

        return $visitor->bindings();
    }

    private function indexedRouteFile(): ParsedFile
    {
        $path = $this->phase4Fixture('routes/api.php');
        $index = (new CodebaseIndexer(new Filesystem(), ['ignore_paths' => []]))->index([$path]);

        return $index[$path];
    }

    private function phase4Fixture(string $path): string
    {
        $fullPath = __DIR__ . '/../../../fixtures/phase4_app/' . str_replace('/', DIRECTORY_SEPARATOR, $path);

        return realpath($fullPath) ?: $fullPath;
    }

    /**
     * @param array<int, \SchemaGuard\ValueObjects\RouteBinding> $bindings
     */
    private function hasBinding(array $bindings, string $verb, string $uri, string $controller, string $method): bool
    {
        foreach ($bindings as $binding) {
            if (
                $binding->verb === $verb
                && $binding->uri === $uri
                && $binding->controllerFqcn === $controller
                && $binding->method === $method
            ) {
                return true;
            }
        }

        return false;
    }
}
