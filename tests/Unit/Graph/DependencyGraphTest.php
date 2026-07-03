<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Graph;

use InvalidArgumentException;
use SchemaGuard\Graph\DependencyGraph;
use SchemaGuard\Graph\GraphNode;
use SchemaGuard\Graph\ImpactPath;
use SchemaGuard\Graph\NodeType;
use SchemaGuard\Tests\TestCase;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\SourceLocation;
use SchemaGuard\ValueObjects\TableReference;

final class DependencyGraphTest extends TestCase
{
    public function test_graph_node_factories_use_stable_ids_and_retain_locations(): void
    {
        $location = new SourceLocation('app/Models/User.php', 12);

        $nodes = [
            GraphNode::column(new ColumnReference('users', 'phone'), $location),
            GraphNode::table(new TableReference('users'), $location),
            GraphNode::model('App\Models\User', $location),
            GraphNode::resource('App\Http\Resources\UserResource', $location),
            GraphNode::action('App\Http\Controllers\UserController', 'show', $location),
            GraphNode::route('get', 'api/users/{user}', $location),
        ];

        $this->assertSame([
            'column:users.phone',
            'table:users',
            'model:App\Models\User',
            'resource:App\Http\Resources\UserResource',
            'action:App\Http\Controllers\UserController@show',
            'route:GET:/api/users/{user}',
        ], array_map(static fn (GraphNode $node): string => $node->id, $nodes));

        $this->assertSame(NodeType::COLUMN, $nodes[0]->type);
        $this->assertSame('users.phone', $nodes[0]->label);
        $this->assertSame($location, $nodes[0]->location);
    }

    public function test_duplicate_nodes_and_edges_are_idempotent(): void
    {
        $graph = new DependencyGraph();
        $column = GraphNode::column(new ColumnReference('users', 'phone'));
        $table = GraphNode::table(new TableReference('users'));

        $graph->addNode($column);
        $graph->addNode($column);
        $graph->addNode($table);
        $graph->addEdge($column->id, $table->id);
        $graph->addEdge($column->id, $table->id);

        $this->assertCount(2, $graph->nodes());
        $this->assertSame([$table->id], $graph->edges()[$column->id]);
    }

    public function test_duplicate_edges_do_not_emit_duplicate_exposed_paths(): void
    {
        $graph = new DependencyGraph();
        $column = GraphNode::column(new ColumnReference('users', 'phone'));
        $resource = GraphNode::resource('App\Http\Resources\UserResource');

        $graph->addNode($column);
        $graph->addNode($resource);
        $graph->addEdge($column->id, $resource->id);
        $graph->addEdge($column->id, $resource->id);

        $this->assertCount(1, $graph->exposedPaths($column->id));
        $this->assertTrue($graph->reachesExposedSurface($column->id));
    }

    public function test_unknown_edge_endpoint_throws_clear_exception(): void
    {
        $graph = new DependencyGraph();
        $graph->addNode(GraphNode::table(new TableReference('users')));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown graph node');

        $graph->addEdge('column:users.phone', 'table:users');
    }

    public function test_reachability_and_exposed_paths_are_cycle_safe_and_deterministic(): void
    {
        $graph = new DependencyGraph();
        $column = GraphNode::column(new ColumnReference('users', 'phone'));
        $model = GraphNode::model('App\Models\User');
        $action = GraphNode::action('App\Http\Controllers\UserController', 'show');
        $route = GraphNode::route('GET', '/api/users/{user}');

        foreach ([$column, $model, $action, $route] as $node) {
            $graph->addNode($node);
        }

        $graph->addEdge($column->id, $model->id);
        $graph->addEdge($model->id, $action->id);
        $graph->addEdge($action->id, $model->id);
        $graph->addEdge($action->id, $route->id);

        $this->assertSame(
            [$model->id, $action->id, $route->id],
            array_map(static fn (GraphNode $node): string => $node->id, $graph->reachableFrom($column->id)),
        );

        $paths = $graph->exposedPaths($column->id);

        $this->assertCount(1, $paths);
        $this->assertSame('users.phone → App\Models\User → UserController@show → GET /api/users/{user}', (string) $paths[0]);
        $this->assertTrue($graph->reachesExposedSurface($column->id));
    }

    public function test_unreachable_and_missing_columns_return_empty_safe_results(): void
    {
        $graph = new DependencyGraph();
        $column = GraphNode::column(new ColumnReference('users', 'phone'));
        $table = GraphNode::table(new TableReference('users'));

        $graph->addNode($column);
        $graph->addNode($table);
        $graph->addEdge($column->id, $table->id);

        $this->assertSame([], $graph->exposedPaths($column->id));
        $this->assertFalse($graph->reachesExposedSurface($column->id));
        $this->assertSame([], $graph->reachableFrom('column:missing.value'));
        $this->assertSame([], $graph->exposedPaths('column:missing.value'));
    }

    public function test_resource_nodes_are_exposed_sinks_but_tables_are_not(): void
    {
        $graph = new DependencyGraph();
        $column = GraphNode::column(new ColumnReference('users', 'phone'));
        $table = GraphNode::table(new TableReference('users'));
        $resource = GraphNode::resource('App\Http\Resources\UserResource');

        foreach ([$column, $table, $resource] as $node) {
            $graph->addNode($node);
        }

        $graph->addEdge($column->id, $table->id);
        $this->assertFalse($graph->reachesExposedSurface($column->id));

        $graph->addEdge($column->id, $resource->id);
        $this->assertTrue($graph->reachesExposedSurface($column->id));
    }

    public function test_impact_path_renders_labels_not_opaque_ids(): void
    {
        $path = new ImpactPath([
            GraphNode::column(new ColumnReference('users', 'phone')),
            GraphNode::model('App\Models\User'),
            GraphNode::route('GET', '/api/users/{user}'),
        ]);

        $this->assertSame('users.phone → App\Models\User → GET /api/users/{user}', (string) $path);
    }

    public function test_impact_path_requires_at_least_one_node(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ImpactPath([]);
    }
}
