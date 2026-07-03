<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Graph;

use Illuminate\Filesystem\Filesystem;
use PhpParser\NodeTraverser;
use SchemaGuard\Graph\DependencyGraphBuilder;
use SchemaGuard\Scanning\CodebaseIndexer;
use SchemaGuard\Scanning\ParsedFile;
use SchemaGuard\Scanning\StaticAnalysisScanner;
use SchemaGuard\Scanning\Visitors\RouteVisitor;
use SchemaGuard\Tests\TestCase;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\SourceLocation;
use SchemaGuard\ValueObjects\SurfaceType;
use SchemaGuard\ValueObjects\Usage;

final class DependencyGraphBuilderTest extends TestCase
{
    public function test_it_builds_exposed_route_paths_from_real_fixture_evidence(): void
    {
        $index = $this->index([
            'app/Models',
            'app/Http/Controllers',
            'app/Http/Resources',
            'routes/api.php',
        ]);

        $event = $this->targetEvent('users.phone');
        $usages = (new StaticAnalysisScanner())->scan($index, [$event]);
        $routes = $this->routeBindings($index[$this->phase4Fixture('routes/api.php')]);
        $graph = (new DependencyGraphBuilder())->build($index, $usages, $routes);
        $paths = array_map(
            static fn ($path): string => (string) $path,
            $graph->exposedPaths('column:users.phone'),
        );

        $this->assertContains(
            'users.phone → App\Models\User → UserController@show → GET /api/users/{user}',
            $paths,
        );
        $this->assertTrue($graph->reachesExposedSurface('column:users.phone'));
    }

    public function test_it_builds_core_relationships_from_real_usage_and_route_evidence(): void
    {
        $index = $this->index([
            'app/Models',
            'app/Http/Controllers',
            'routes/api.php',
        ]);

        $event = $this->targetEvent('users.phone');
        $usages = (new StaticAnalysisScanner())->scan($index, [$event]);
        $routes = $this->routeBindings($index[$this->phase4Fixture('routes/api.php')]);
        $graph = (new DependencyGraphBuilder())->build($index, $usages, $routes);
        $edges = $graph->edges();

        $this->assertContains('table:users', $edges['column:users.phone']);
        $this->assertContains('model:App\Models\User', $edges['column:users.phone']);
        $this->assertContains('table:users', $edges['model:App\Models\User']);
        $this->assertContains('action:App\Http\Controllers\UserController@show', $edges['model:App\Models\User']);
        $this->assertContains('controller:App\Http\Controllers\UserController', $edges['action:App\Http\Controllers\UserController@show']);
        $this->assertContains('model:App\Models\User', $edges['controller:App\Http\Controllers\UserController']);
        $this->assertContains('action:App\Http\Controllers\UserController@show', $edges['route:GET:/api/users/{user}']);
    }

    public function test_it_builds_resource_relationships_from_api_resource_usage(): void
    {
        $index = $this->index([
            'app/Models',
            'app/Http/Resources',
        ]);

        $event = $this->targetEvent('users.phone');
        $usages = (new StaticAnalysisScanner())->scan($index, [$event]);
        $graph = (new DependencyGraphBuilder())->build($index, $usages, []);
        $edges = $graph->edges();

        $this->assertContains('resource:App\Http\Resources\UserResource', $edges['column:users.phone']);
        $this->assertContains('model:App\Models\User', $edges['resource:App\Http\Resources\UserResource']);
        $this->assertTrue($graph->reachesExposedSurface('column:users.phone'));
    }

    public function test_model_usage_without_route_or_resource_evidence_does_not_create_public_exposure(): void
    {
        $index = $this->index([
            'app/Models',
        ]);

        $usage = new Usage(
            new ColumnReference('users', 'phone'),
            SurfaceType::MODEL_SCHEMA,
            Confidence::DEFINITIVE,
            new SourceLocation($this->phase4Fixture('app/Models/User.php'), 10),
            '$fillable',
        );

        $graph = (new DependencyGraphBuilder())->build($index, [$usage], []);
        $edges = $graph->edges();

        $this->assertContains('table:users', $edges['column:users.phone']);
        $this->assertContains('model:App\Models\User', $edges['column:users.phone']);
        $this->assertFalse($graph->reachesExposedSurface('column:users.phone'));
        $this->assertSame([], $graph->exposedPaths('column:users.phone'));
    }

    /**
     * @return array<int, \SchemaGuard\ValueObjects\RouteBinding>
     */
    private function routeBindings(ParsedFile $file): array
    {
        $visitor = new RouteVisitor();
        $visitor->reset($file);
        (new NodeTraverser($visitor))->traverse($file->ast ?? []);

        return $visitor->bindings();
    }

    /**
     * @param string[] $paths
     *
     * @return array<string, ParsedFile>
     */
    private function index(array $paths): array
    {
        return (new CodebaseIndexer(new Filesystem(), ['ignore_paths' => []]))->index(
            array_map(fn (string $path): string => $this->phase4Fixture($path), $paths),
        );
    }

    private function targetEvent(string $symbol): SchemaChangeEvent
    {
        [$table, $column] = explode('.', $symbol, 2);

        return SchemaChangeEvent::columnDropped(
            new ColumnReference($table, $column),
            new SourceLocation('migration.php', 1),
        );
    }

    private function phase4Fixture(string $path): string
    {
        $fullPath = __DIR__ . '/../../../fixtures/phase4_app/' . str_replace('/', DIRECTORY_SEPARATOR, $path);

        return realpath($fullPath) ?: $fullPath;
    }
}
