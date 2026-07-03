<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Pipeline;

use Illuminate\Filesystem\Filesystem;
use SchemaGuard\Exceptions\CodebaseScanException;
use SchemaGuard\Graph\DependencyGraphBuilder;
use SchemaGuard\Migrations\MigrationDiscovery;
use SchemaGuard\Migrations\MigrationParser;
use SchemaGuard\Pipeline\AnalysisPipeline;
use SchemaGuard\Pipeline\AnalysisRequest;
use SchemaGuard\Pipeline\MigrationSource;
use SchemaGuard\Pipeline\OutputFormat;
use SchemaGuard\Policy\PolicyConfiguration;
use SchemaGuard\Policy\PolicyEngine;
use SchemaGuard\Scanning\CodebaseIndexer;
use SchemaGuard\Scanning\StaticAnalysisScanner;
use SchemaGuard\Scanning\Visitors\RouteVisitor;
use SchemaGuard\Tests\TestCase;
use SchemaGuard\ValueObjects\Severity;

final class AnalysisPipelineTest extends TestCase
{
    public function test_no_destructive_events_short_circuits_without_indexing(): void
    {
        $progressEvents = [];

        $result = $this->pipeline()->run(new AnalysisRequest(
            scanPaths: ['definitely-missing-scan-root'],
            migrationSource: MigrationSource::EXPLICIT,
            gitBase: 'HEAD',
            explicitMigrations: [$this->migration('2024_06_08_000000_non_destructive_changes.php')],
            format: OutputFormat::CONSOLE,
            strict: false,
            useCache: true,
            scanPathsWereProvided: true,
        ), static function (string $event, mixed $value) use (&$progressEvents): void {
            $progressEvents[] = [$event, $value];
        });

        $this->assertSame(Severity::SAFE, $result->policyResult->overall);
        $this->assertSame(1, $result->metadata->migrationCount);
        $this->assertSame(0, $result->metadata->indexedFileCount);
        $this->assertSame(0, $result->metadata->unparsedFileCount);
        $this->assertSame([], $progressEvents);
    }

    public function test_it_runs_full_pipeline_and_reports_source_parse_degradation(): void
    {
        $result = $this->pipeline()->run(new AnalysisRequest(
            scanPaths: [$this->fixturesPath()],
            migrationSource: MigrationSource::EXPLICIT,
            gitBase: 'HEAD',
            explicitMigrations: [$this->migration('2024_06_01_000000_drop_phone_from_users.php')],
            format: OutputFormat::CONSOLE,
            strict: false,
            useCache: true,
            scanPathsWereProvided: true,
        ));

        $this->assertSame(Severity::BLOCK, $result->policyResult->overall);
        $this->assertSame(1, $result->metadata->migrationCount);
        $this->assertGreaterThan(0, $result->metadata->indexedFileCount);
        $this->assertSame(1, $result->metadata->unparsedFileCount);
        $this->assertNotEmpty($result->policyResult->findings[0]->usages);
        $this->assertStringContainsString('broken_syntax.php', implode("\n", $result->policyResult->diagnostics));
    }

    public function test_missing_scan_root_is_a_fatal_scan_error_when_events_exist(): void
    {
        $this->expectException(CodebaseScanException::class);

        $this->pipeline()->run(new AnalysisRequest(
            scanPaths: ['missing-scan-root'],
            migrationSource: MigrationSource::EXPLICIT,
            gitBase: 'HEAD',
            explicitMigrations: [$this->migration('2024_06_01_000000_drop_phone_from_users.php')],
            format: OutputFormat::CONSOLE,
            strict: false,
            useCache: true,
            scanPathsWereProvided: true,
        ));
    }

    public function test_progress_callback_receives_indexing_events(): void
    {
        $events = [];

        $this->pipeline()->run(new AnalysisRequest(
            scanPaths: [$this->fixture('Models/User.php')],
            migrationSource: MigrationSource::EXPLICIT,
            gitBase: 'HEAD',
            explicitMigrations: [$this->migration('2024_06_01_000000_drop_phone_from_users.php')],
            format: OutputFormat::CONSOLE,
            strict: false,
            useCache: true,
            scanPathsWereProvided: true,
        ), static function (string $event, mixed $value) use (&$events): void {
            $events[] = [$event, $value];
        });

        $this->assertSame('start', $events[0][0]);
        $this->assertSame(1, $events[0][1]);
        $this->assertSame('finish', $events[array_key_last($events)][0]);
    }

    private function pipeline(): AnalysisPipeline
    {
        $files = new Filesystem();
        $config = PolicyConfiguration::fromArray([
            'scan_paths' => [$this->fixturesPath()],
            'migration_paths' => [$this->migrationsPath()],
            'policy' => [
                'modes' => [],
                'escalate_exposed_to_block' => false,
                'block_confidence_floor' => 'high',
            ],
            'ignore_paths' => [],
            'ignore' => ['tables' => [], 'columns' => []],
            'enforce' => ['tables' => [], 'columns' => []],
            'custom_rules' => [],
            'exit_codes' => [
                'warning_exit_code' => 0,
                'treat_warnings_as_failure' => false,
            ],
        ]);

        return new AnalysisPipeline(
            new MigrationDiscovery($files, $config),
            new MigrationParser($files),
            new CodebaseIndexer($files, ['ignore_paths' => []]),
            new StaticAnalysisScanner(),
            new RouteVisitor(),
            new DependencyGraphBuilder(),
            new PolicyEngine($config),
        );
    }

    private function fixturesPath(): string
    {
        return realpath(__DIR__ . '/../../Fixtures') ?: __DIR__ . '/../../Fixtures';
    }

    private function migrationsPath(): string
    {
        return $this->fixturesPath() . DIRECTORY_SEPARATOR . 'migrations';
    }

    private function fixture(string $path): string
    {
        $fullPath = $this->fixturesPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

        return realpath($fullPath) ?: $fullPath;
    }

    private function migration(string $name): string
    {
        return $this->migrationsPath() . DIRECTORY_SEPARATOR . $name;
    }
}
