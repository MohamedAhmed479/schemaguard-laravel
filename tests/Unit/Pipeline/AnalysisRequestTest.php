<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Pipeline;

use SchemaGuard\Exceptions\ConfigurationException;
use SchemaGuard\Pipeline\AnalysisRequest;
use SchemaGuard\Pipeline\MigrationSource;
use SchemaGuard\Pipeline\OutputFormat;
use SchemaGuard\Policy\PolicyConfiguration;
use SchemaGuard\Tests\TestCase;

final class AnalysisRequestTest extends TestCase
{
    public function test_it_builds_default_pending_console_request_from_config(): void
    {
        $request = AnalysisRequest::fromCommandOptions([], $this->config());

        $this->assertSame(['app', 'routes'], $request->scanPaths);
        $this->assertSame(MigrationSource::PENDING, $request->migrationSource);
        $this->assertSame('HEAD', $request->gitBase);
        $this->assertSame([], $request->explicitMigrations);
        $this->assertSame(OutputFormat::CONSOLE, $request->format);
        $this->assertFalse($request->strict);
        $this->assertTrue($request->useCache);
        $this->assertFalse($request->scanPathsWereProvided);
    }

    public function test_explicit_migrations_select_explicit_source(): void
    {
        $request = AnalysisRequest::fromCommandOptions([
            'migrations' => ['database/migrations/drop.php'],
        ], $this->config());

        $this->assertSame(MigrationSource::EXPLICIT, $request->migrationSource);
        $this->assertSame(['database/migrations/drop.php'], $request->explicitMigrations);
    }

    public function test_diff_mode_selects_git_diff_source_with_base(): void
    {
        $request = AnalysisRequest::fromCommandOptions([
            'diff' => true,
            'base' => 'origin/main',
        ], $this->config());

        $this->assertSame(MigrationSource::GIT_DIFF, $request->migrationSource);
        $this->assertSame('origin/main', $request->gitBase);
    }

    public function test_path_strict_json_and_no_cache_options_are_represented(): void
    {
        $request = AnalysisRequest::fromCommandOptions([
            'path' => ['tests/Fixtures', 'tests/Fixtures'],
            'format' => 'json',
            'strict' => true,
            'no-cache' => true,
        ], $this->config());

        $this->assertSame(['tests/Fixtures'], $request->scanPaths);
        $this->assertSame(OutputFormat::JSON, $request->format);
        $this->assertTrue($request->strict);
        $this->assertFalse($request->useCache);
        $this->assertTrue($request->scanPathsWereProvided);
    }

    public function test_invalid_format_throws_configuration_exception(): void
    {
        $this->expectException(ConfigurationException::class);

        AnalysisRequest::fromCommandOptions(['format' => 'xml'], $this->config());
    }

    public function test_diff_and_explicit_migrations_conflict(): void
    {
        $this->expectException(ConfigurationException::class);

        AnalysisRequest::fromCommandOptions([
            'diff' => true,
            'migrations' => ['database/migrations/drop.php'],
        ], $this->config());
    }

    private function config(): PolicyConfiguration
    {
        return PolicyConfiguration::fromArray([
            'scan_paths' => ['app', 'routes'],
            'migration_paths' => ['database/migrations'],
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
    }
}
