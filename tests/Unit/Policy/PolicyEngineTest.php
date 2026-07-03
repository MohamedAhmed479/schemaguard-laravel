<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Policy;

use PHPUnit\Framework\Attributes\DataProvider;
use SchemaGuard\Graph\DependencyGraph;
use SchemaGuard\Graph\GraphNode;
use SchemaGuard\Policy\PolicyConfiguration;
use SchemaGuard\Policy\PolicyEngine;
use SchemaGuard\Policy\PolicyResult;
use SchemaGuard\Tests\TestCase;
use SchemaGuard\ValueObjects\ChangeType;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\Severity;
use SchemaGuard\ValueObjects\SourceLocation;
use SchemaGuard\ValueObjects\SurfaceType;
use SchemaGuard\ValueObjects\TableReference;
use SchemaGuard\ValueObjects\Usage;

final class PolicyEngineTest extends TestCase
{
    #[DataProvider('matrixCases')]
    public function test_decision_matrix(ChangeType $type, ?Confidence $confidence, Severity $expected): void
    {
        $event = $this->event($type);
        $usages = $confidence === null ? [] : [$this->usage($event, $confidence)];
        $result = $this->engine()->evaluate([$event], $usages, new DependencyGraph());

        $this->assertSame($expected, $result->overall);
        $this->assertSame($expected, $result->findings[0]->severity);
    }

    /**
     * @return array<string, array{0:ChangeType,1:?Confidence,2:Severity}>
     */
    public static function matrixCases(): array
    {
        return [
            'column dropped high' => [ChangeType::COLUMN_DROPPED, Confidence::DEFINITIVE, Severity::BLOCK],
            'column dropped ambiguous' => [ChangeType::COLUMN_DROPPED, Confidence::MEDIUM, Severity::WARNING],
            'column dropped none' => [ChangeType::COLUMN_DROPPED, null, Severity::SAFE],
            'column renamed high' => [ChangeType::COLUMN_RENAMED, Confidence::HIGH, Severity::BLOCK],
            'column renamed ambiguous' => [ChangeType::COLUMN_RENAMED, Confidence::LOW, Severity::WARNING],
            'column renamed none' => [ChangeType::COLUMN_RENAMED, null, Severity::SAFE],
            'table dropped high' => [ChangeType::TABLE_DROPPED, Confidence::HIGH, Severity::BLOCK],
            'table dropped ambiguous' => [ChangeType::TABLE_DROPPED, Confidence::MEDIUM, Severity::WARNING],
            'table dropped none' => [ChangeType::TABLE_DROPPED, null, Severity::SAFE],
            'type changed high' => [ChangeType::COLUMN_TYPE_CHANGED, Confidence::DEFINITIVE, Severity::WARNING],
            'type changed ambiguous' => [ChangeType::COLUMN_TYPE_CHANGED, Confidence::LOW, Severity::WARNING],
            'type changed none' => [ChangeType::COLUMN_TYPE_CHANGED, null, Severity::SAFE],
        ];
    }

    public function test_policy_result_counts_are_derived_from_findings(): void
    {
        $events = [
            $this->event(ChangeType::COLUMN_DROPPED, 'phone'),
            $this->event(ChangeType::COLUMN_RENAMED, 'name'),
            $this->event(ChangeType::COLUMN_TYPE_CHANGED, 'email'),
        ];
        $usages = [
            $this->usage($events[0], Confidence::DEFINITIVE),
            $this->usage($events[2], Confidence::HIGH),
        ];

        $result = $this->engine()->evaluate($events, $usages, new DependencyGraph());

        $this->assertSame(Severity::BLOCK, $result->overall);
        $this->assertSame(1, $result->blockCount);
        $this->assertSame(1, $result->warningCount);
        $this->assertSame(1, $result->safeCount);
    }

    public function test_policy_result_empty_is_safe_with_zero_counts(): void
    {
        $empty = PolicyResult::empty();

        $this->assertSame(Severity::SAFE, $empty->overall);
        $this->assertSame(0, $empty->blockCount);
        $this->assertSame(0, $empty->warningCount);
        $this->assertSame(0, $empty->safeCount);
        $this->assertSame([], $empty->findings);
    }

    public function test_table_drop_matches_all_column_usages_for_that_table_and_keeps_paths(): void
    {
        $event = SchemaChangeEvent::tableDropped(new TableReference('users'), new SourceLocation('migration.php', 1));
        $phoneUsage = $this->columnUsage('users', 'phone', Confidence::HIGH);
        $emailUsage = $this->columnUsage('users', 'email', Confidence::MEDIUM);
        $otherUsage = $this->columnUsage('posts', 'phone', Confidence::DEFINITIVE);
        $graph = $this->multiColumnExposedGraph();

        $result = $this->engine()->evaluate([$event], [$phoneUsage, $emailUsage, $otherUsage], $graph);

        $this->assertSame(Severity::BLOCK, $result->overall);
        $this->assertCount(2, $result->findings[0]->usages);
        $this->assertSame(['users.phone', 'users.email'], array_map(
            static fn (Usage $usage): string => "{$usage->symbol->table}.{$usage->symbol->column}",
            $result->findings[0]->usages,
        ));
        $this->assertSame([
            'users.email → App\Http\Resources\UserResource',
            'users.phone → GET /api/users/{user}',
        ], array_map(static fn ($path): string => (string) $path, $result->findings[0]->paths));
    }

    public function test_rename_event_matches_original_column_and_not_the_new_name(): void
    {
        $event = SchemaChangeEvent::columnRenamed(
            new ColumnReference('users', 'full_name'),
            'name',
            new SourceLocation('migration.php', 1),
        );

        $result = $this->engine()->evaluate(
            [$event],
            [
                $this->columnUsage('users', 'full_name', Confidence::HIGH),
                $this->columnUsage('users', 'name', Confidence::DEFINITIVE),
            ],
            new DependencyGraph(),
        );

        $this->assertSame(Severity::BLOCK, $result->overall);
        $this->assertCount(1, $result->findings[0]->usages);
        $this->assertSame('full_name', $result->findings[0]->usages[0]->symbol->column);
        $this->assertSame('name', $result->findings[0]->event->renamedTo);
    }

    public function test_ignore_forces_safe_even_with_definitive_usage(): void
    {
        $event = $this->event(ChangeType::COLUMN_DROPPED);
        $result = $this->engine([
            'ignore' => ['columns' => ['users.phone']],
        ])->evaluate([$event], [$this->usage($event, Confidence::DEFINITIVE)], new DependencyGraph());

        $this->assertSame(Severity::SAFE, $result->overall);
    }

    public function test_enforce_forces_block_even_with_zero_usages(): void
    {
        $event = $this->event(ChangeType::COLUMN_DROPPED);
        $result = $this->engine([
            'enforce' => ['columns' => ['users.phone']],
        ])->evaluate([$event], [], new DependencyGraph());

        $this->assertSame(Severity::BLOCK, $result->overall);
    }

    public function test_per_type_warn_mode_downgrades_would_be_block_to_warning(): void
    {
        $event = $this->event(ChangeType::COLUMN_DROPPED);
        $result = $this->engine([
            'policy' => [
                'modes' => [
                    'column_dropped' => 'warn',
                ],
            ],
        ])->evaluate([$event], [$this->usage($event, Confidence::DEFINITIVE)], new DependencyGraph());

        $this->assertSame(Severity::WARNING, $result->overall);
    }

    public function test_custom_rule_has_highest_override_precedence(): void
    {
        $event = $this->event(ChangeType::COLUMN_DROPPED);
        $result = $this->engine([
            'ignore' => ['columns' => ['users.phone']],
            'enforce' => ['columns' => ['users.phone']],
            'policy' => [
                'modes' => [
                    'column_dropped' => 'off',
                ],
            ],
            'custom_rules' => [
                ['change_type' => 'column_dropped', 'table' => 'users', 'column' => 'phone', 'severity' => 'block'],
            ],
        ])->evaluate([$event], [], new DependencyGraph());

        $this->assertSame(Severity::BLOCK, $result->overall);
    }

    public function test_ignored_target_with_matching_custom_rule_ends_with_custom_rule_severity(): void
    {
        $event = $this->event(ChangeType::COLUMN_DROPPED);
        $result = $this->engine([
            'ignore' => ['columns' => ['users.phone']],
            'custom_rules' => [
                ['change_type' => 'column_dropped', 'table' => 'users', 'column' => 'phone', 'severity' => 'block'],
            ],
        ])->evaluate([$event], [$this->usage($event, Confidence::DEFINITIVE)], new DependencyGraph());

        $this->assertSame(Severity::BLOCK, $result->overall);
    }

    public function test_enforced_target_with_warn_mode_is_clamped_to_warning_by_precedence(): void
    {
        $event = $this->event(ChangeType::COLUMN_DROPPED);
        $result = $this->engine([
            'enforce' => ['columns' => ['users.phone']],
            'policy' => [
                'modes' => [
                    'column_dropped' => 'warn',
                ],
            ],
        ])->evaluate([$event], [], new DependencyGraph());

        $this->assertSame(Severity::WARNING, $result->overall);
    }

    public function test_exposure_escalation_only_happens_when_configured(): void
    {
        $event = $this->event(ChangeType::COLUMN_DROPPED);
        $usage = $this->usage($event, Confidence::MEDIUM);
        $graph = $this->exposedGraph();

        $withoutEscalation = $this->engine()->evaluate([$event], [$usage], $graph);
        $withEscalation = $this->engine([
            'policy' => ['escalate_exposed_to_block' => true],
        ])->evaluate([$event], [$usage], $graph);

        $this->assertSame(Severity::WARNING, $withoutEscalation->overall);
        $this->assertSame(Severity::BLOCK, $withEscalation->overall);
    }

    public function test_exposure_escalation_does_not_block_no_usage_events_or_disconnected_graph_nodes(): void
    {
        $event = $this->event(ChangeType::COLUMN_DROPPED);
        $graph = $this->exposedGraph('email');

        $result = $this->engine([
            'policy' => ['escalate_exposed_to_block' => true],
        ])->evaluate([$event], [], $graph);

        $this->assertSame(Severity::SAFE, $result->overall);
        $this->assertSame([], $result->findings[0]->paths);
    }

    public function test_block_confidence_floor_is_respected(): void
    {
        $event = $this->event(ChangeType::COLUMN_DROPPED);
        $engine = $this->engine([
            'policy' => ['block_confidence_floor' => 'definitive'],
        ]);

        $high = $engine->evaluate([$event], [$this->usage($event, Confidence::HIGH)], new DependencyGraph());
        $medium = $engine->evaluate([$event], [$this->usage($event, Confidence::MEDIUM)], new DependencyGraph());
        $definitive = $engine->evaluate([$event], [$this->usage($event, Confidence::DEFINITIVE)], new DependencyGraph());

        $this->assertSame(Severity::WARNING, $high->overall);
        $this->assertSame(Severity::WARNING, $medium->overall);
        $this->assertSame(Severity::BLOCK, $definitive->overall);
    }

    public function test_indeterminate_events_warn_and_emit_diagnostics(): void
    {
        $event = SchemaChangeEvent::indeterminate(
            ChangeType::COLUMN_DROPPED,
            new TableReference('users'),
            'dynamic column name',
            new SourceLocation('migration.php', 10),
        );

        $result = $this->engine()->evaluate([$event], [], new DependencyGraph());

        $this->assertSame(Severity::WARNING, $result->overall);
        $this->assertCount(1, $result->diagnostics);
    }

    public function test_neutralized_events_are_safe_and_emit_diagnostics_even_with_usage(): void
    {
        $event = SchemaChangeEvent::columnDropped(
            new ColumnReference('users', 'phone'),
            new SourceLocation('migration.php', 10),
        )->neutralized('Drop of users.phone was neutralized by a same-migration re-add.');

        $result = $this->engine()->evaluate([$event], [$this->usage($event, Confidence::DEFINITIVE)], new DependencyGraph());

        $this->assertSame(Severity::SAFE, $result->overall);
        $this->assertSame(Severity::SAFE, $result->findings[0]->severity);
        $this->assertCount(1, $result->diagnostics);
        $this->assertStringContainsString('Neutralized', $result->diagnostics[0]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function engine(array $config = []): PolicyEngine
    {
        return new PolicyEngine(PolicyConfiguration::fromArray($this->config($config)));
    }

    private function event(ChangeType $type, string $column = 'phone'): SchemaChangeEvent
    {
        $location = new SourceLocation('migration.php', 1);

        return match ($type) {
            ChangeType::COLUMN_DROPPED => SchemaChangeEvent::columnDropped(new ColumnReference('users', $column), $location),
            ChangeType::COLUMN_RENAMED => SchemaChangeEvent::columnRenamed(new ColumnReference('users', $column), 'new_' . $column, $location),
            ChangeType::TABLE_DROPPED => SchemaChangeEvent::tableDropped(new TableReference('users'), $location),
            ChangeType::COLUMN_TYPE_CHANGED => SchemaChangeEvent::columnTypeChanged(new ColumnReference('users', $column), 'string', $location),
        };
    }

    private function usage(SchemaChangeEvent $event, Confidence $confidence): Usage
    {
        $symbol = $event->column ?? new ColumnReference($event->table?->table ?? 'users', 'phone');

        return new Usage(
            $symbol,
            SurfaceType::ELOQUENT_QUERY,
            $confidence,
            new SourceLocation('app.php', 5),
            'test',
        );
    }

    private function columnUsage(string $table, string $column, Confidence $confidence): Usage
    {
        return new Usage(
            new ColumnReference($table, $column),
            SurfaceType::ELOQUENT_QUERY,
            $confidence,
            new SourceLocation('app.php', 5),
            'test',
        );
    }

    private function exposedGraph(string $columnName = 'phone'): DependencyGraph
    {
        $graph = new DependencyGraph();
        $column = GraphNode::column(new ColumnReference('users', $columnName));
        $route = GraphNode::route('GET', '/api/users/{user}');

        $graph->addNode($column);
        $graph->addNode($route);
        $graph->addEdge($column->id, $route->id);

        return $graph;
    }

    private function multiColumnExposedGraph(): DependencyGraph
    {
        $graph = new DependencyGraph();
        $phone = GraphNode::column(new ColumnReference('users', 'phone'));
        $email = GraphNode::column(new ColumnReference('users', 'email'));
        $route = GraphNode::route('GET', '/api/users/{user}');
        $resource = GraphNode::resource('App\Http\Resources\UserResource');

        foreach ([$phone, $email, $route, $resource] as $node) {
            $graph->addNode($node);
        }

        $graph->addEdge($phone->id, $route->id);
        $graph->addEdge($email->id, $resource->id);

        return $graph;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        return array_replace_recursive([
            'policy' => [
                'modes' => [],
                'escalate_exposed_to_block' => false,
                'block_confidence_floor' => 'high',
            ],
            'ignore_paths' => [],
            'ignore' => [
                'tables' => [],
                'columns' => [],
            ],
            'enforce' => [
                'tables' => [],
                'columns' => [],
            ],
            'custom_rules' => [],
            'exit_codes' => [
                'warning_exit_code' => 0,
                'treat_warnings_as_failure' => false,
            ],
        ], $overrides);
    }
}
