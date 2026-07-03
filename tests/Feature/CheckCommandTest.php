<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use SchemaGuard\Console\Commands\CheckCommand;
use SchemaGuard\Migrations\MigrationDiscovery;
use SchemaGuard\Output\ExitCodeResolver;
use SchemaGuard\Pipeline\AnalysisPipeline;
use SchemaGuard\Policy\PolicyConfiguration;
use SchemaGuard\Tests\TestCase;

final class CheckCommandTest extends TestCase
{
    public function test_command_runs_real_pipeline_and_blocks_used_dropped_column(): void
    {
        $this->configureForFixtures();

        $this->artisan('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_01_000000_drop_phone_from_users.php')],
            '--path' => [$this->fixturesPath()],
        ])
            ->expectsOutputToContain('Indexing source files')
            ->expectsOutputToContain('Deployment Firewall')
            ->expectsOutputToContain('MODEL_SCHEMA')
            ->expectsOutputToContain('Blast radius')
            ->expectsOutputToContain('RESULT: BLOCK')
            ->assertExitCode(1);
    }

    public function test_command_returns_safe_for_unused_dropped_column(): void
    {
        $this->configureForFixtures();

        $this->artisan('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_15_000000_drop_unused_column_from_users.php')],
            '--path' => [$this->fixturesPath()],
        ])
            ->expectsOutputToContain('RESULT: SAFE')
            ->assertExitCode(0);
    }

    public function test_used_type_change_warns_by_default_and_fails_under_strict(): void
    {
        $this->configureForFixtures();

        $this->artisan('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_16_000000_change_user_phone_type.php')],
            '--path' => [$this->fixturesPath()],
        ])
            ->expectsOutputToContain('RESULT: WARNING')
            ->assertExitCode(0);

        $this->refreshApplication();
        $this->configureForFixtures();

        $this->artisan('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_16_000000_change_user_phone_type.php')],
            '--path' => [$this->fixturesPath()],
            '--strict' => true,
        ])
            ->expectsOutputToContain('RESULT: WARNING')
            ->assertExitCode(1);
    }

    public function test_renamed_column_still_referenced_blocks(): void
    {
        $this->configureForFixtures();

        $exitCode = Artisan::call('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_02_000000_rename_users_column.php')],
            '--path' => [
                $this->fixture('Models'),
                $this->fixture('rename'),
            ],
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('COLUMN_RENAMED', $output);
        $this->assertStringContainsString('full_name -> name', $output);
        $this->assertStringContainsString('RESULT: BLOCK', $output);
    }

    public function test_json_mode_outputs_only_valid_json_document(): void
    {
        $this->configureForFixtures();

        $exitCode = Artisan::call('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_01_000000_drop_phone_from_users.php')],
            '--path' => [$this->fixturesPath()],
            '--format' => 'json',
        ]);
        $output = Artisan::output();
        $decoded = $this->assertStrictJsonDocument($output);

        $this->assertSame(1, $exitCode);
        $this->assertSame('1.0', $decoded['schema_version']);
        $this->assertSame('BLOCK', $decoded['overall']);
        $this->assertSame(1, $decoded['exit_code']);
        $this->assertSame(1, $decoded['counts']['block']);
        $this->assertNotEmpty($decoded['findings']);
        $this->assertArrayHasKey('diagnostics', $decoded);
        $this->assertArrayHasKey('analyzed', $decoded);
        $this->assertGreaterThanOrEqual(1, $decoded['analyzed']['unparsed_files']);
        $this->assertStringContainsString('broken_syntax.php', implode("\n", $decoded['diagnostics']));
        $this->assertStringContainsString('Indeterminate raw SQL', implode("\n", $decoded['diagnostics']));
        $this->assertArrayHasKey('usages', $decoded['findings'][0]);
        $this->assertArrayHasKey('impact_paths', $decoded['findings'][0]);
    }

    public function test_raw_sql_only_usage_blocks_dropped_column(): void
    {
        $this->configureForFixtures();

        $this->artisan('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_01_000000_drop_phone_from_users.php')],
            '--path' => [$this->fixture('sql')],
        ])
            ->expectsOutputToContain('RAW_SQL')
            ->expectsOutputToContain('Indeterminate raw SQL')
            ->expectsOutputToContain('RESULT: BLOCK')
            ->assertExitCode(1);
    }

    public function test_neutralized_same_migration_drop_is_safe_with_diagnostic(): void
    {
        $this->configureForFixtures();

        $this->artisan('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_18_000000_drop_and_readd_user_phone.php')],
            '--path' => [$this->fixturesPath()],
        ])
            ->expectsOutputToContain('Neutralized')
            ->expectsOutputToContain('RESULT: SAFE')
            ->assertExitCode(0);
    }

    public function test_neutralized_same_migration_drop_is_safe_in_json_with_diagnostic(): void
    {
        $this->configureForFixtures();

        $exitCode = Artisan::call('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_18_000000_drop_and_readd_user_phone.php')],
            '--path' => [$this->fixturesPath()],
            '--format' => 'json',
        ]);
        $decoded = $this->assertStrictJsonDocument(Artisan::output());

        $this->assertSame(0, $exitCode);
        $this->assertSame('SAFE', $decoded['overall']);
        $this->assertTrue($decoded['findings'][0]['neutralized']);
        $this->assertStringContainsString('same-migration re-add', $decoded['findings'][0]['reason']);
        $this->assertStringContainsString('Neutralized', implode("\n", $decoded['diagnostics']));
    }

    public function test_enforced_table_drop_blocks_even_with_zero_usages(): void
    {
        $this->configureForFixtures();
        config()->set('schemaguard.enforce.tables', ['legacy_logs']);
        $this->forgetPhaseFiveInstances();

        $exitCode = Artisan::call('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_03_000000_drop_legacy_logs_table.php')],
            '--path' => [$this->fixture('Models')],
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('TABLE_DROPPED', $output);
        $this->assertStringContainsString('legacy_logs', $output);
        $this->assertStringContainsString('RESULT: BLOCK', $output);
    }

    public function test_ignored_used_column_is_safe(): void
    {
        $this->configureForFixtures();
        config()->set('schemaguard.ignore.columns', ['users.phone']);
        $this->forgetPhaseFiveInstances();

        $this->artisan('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_01_000000_drop_phone_from_users.php')],
            '--path' => [$this->fixturesPath()],
        ])
            ->expectsOutputToContain('RESULT: SAFE')
            ->assertExitCode(0);
    }

    public function test_broken_source_file_is_reported_while_verdict_continues(): void
    {
        $this->configureForFixtures();

        $exitCode = Artisan::call('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_01_000000_drop_phone_from_users.php')],
            '--path' => [$this->fixturesPath()],
            '--format' => 'json',
        ]);
        $decoded = $this->assertStrictJsonDocument(Artisan::output());

        $this->assertSame(1, $exitCode);
        $this->assertSame('BLOCK', $decoded['overall']);
        $this->assertGreaterThanOrEqual(1, $decoded['analyzed']['unparsed_files']);
        $this->assertStringContainsString('broken_syntax.php', implode("\n", $decoded['diagnostics']));
    }

    public function test_golden_json_output_matches_contract(): void
    {
        $this->configureForFixtures();

        $exitCode = Artisan::call('schemaguard:check', [
            '--migrations' => [$this->fixture('golden/migrations/2024_06_19_000000_drop_phone_from_users.php')],
            '--path' => [
                $this->fixture('golden/app'),
                $this->fixture('golden/routes'),
            ],
            '--format' => 'json',
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStrictJsonDocument($output);
        $this->assertJsonStringEqualsJsonFile($this->fixture('golden/expected.json'), $output);
    }

    public function test_json_mode_reports_safe_without_console_fragments(): void
    {
        $this->configureForFixtures();

        $exitCode = Artisan::call('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_15_000000_drop_unused_column_from_users.php')],
            '--path' => [$this->fixturesPath()],
            '--format' => 'json',
        ]);
        $decoded = $this->assertStrictJsonDocument(Artisan::output());

        $this->assertSame(0, $exitCode);
        $this->assertSame('SAFE', $decoded['overall']);
        $this->assertSame(0, $decoded['exit_code']);
    }

    public function test_json_mode_reports_warning_exit_code_without_console_fragments(): void
    {
        $this->configureForFixtures();

        $exitCode = Artisan::call('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_16_000000_change_user_phone_type.php')],
            '--path' => [$this->fixturesPath()],
            '--format' => 'json',
        ]);
        $decoded = $this->assertStrictJsonDocument(Artisan::output());

        $this->assertSame(0, $exitCode);
        $this->assertSame('WARNING', $decoded['overall']);
        $this->assertSame(0, $decoded['exit_code']);
    }

    public function test_missing_scan_root_fails_clearly_and_json_error_is_valid(): void
    {
        $this->configureForFixtures();

        $exitCode = Artisan::call('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_01_000000_drop_phone_from_users.php')],
            '--path' => ['missing-scan-root'],
            '--format' => 'json',
        ]);
        $decoded = $this->assertStrictJsonDocument(Artisan::output());

        $this->assertSame(1, $exitCode);
        $this->assertSame('ERROR', $decoded['overall']);
        $this->assertStringContainsString('Scan path does not exist', $decoded['error']['message']);
    }

    public function test_missing_scan_root_console_error_is_not_a_normal_result(): void
    {
        $this->configureForFixtures();

        $exitCode = Artisan::call('schemaguard:check', [
            '--migrations' => [$this->migration('2024_06_01_000000_drop_phone_from_users.php')],
            '--path' => ['missing-scan-root'],
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('SchemaGuard failed', $output);
        $this->assertStringContainsString('Scan path does not exist', $output);
        $this->assertStringNotContainsString('RESULT:', $output);
        $this->assertStringNotContainsString('Stack trace', $output);
    }

    public function test_default_command_is_safe_when_no_migrations_are_discovered(): void
    {
        $this->configureForFixtures(migrationPaths: [$this->fixturesPath() . DIRECTORY_SEPARATOR . 'empty-migrations']);

        $this->artisan('schemaguard:check')
            ->expectsOutputToContain('Deployment Firewall')
            ->expectsOutputToContain('RESULT: SAFE')
            ->assertExitCode(0);
    }

    /**
     * @param string[]|null $migrationPaths
     */
    private function configureForFixtures(?array $migrationPaths = null): void
    {
        config()->set('schemaguard.scan_paths', [$this->fixturesPath()]);
        config()->set('schemaguard.migration_paths', $migrationPaths ?? [$this->migrationsPath()]);
        config()->set('schemaguard.ignore_paths', []);
        config()->set('schemaguard.ignore.tables', []);
        config()->set('schemaguard.ignore.columns', []);
        config()->set('schemaguard.enforce.tables', []);
        config()->set('schemaguard.enforce.columns', []);
        config()->set('schemaguard.custom_rules', []);
        config()->set('schemaguard.policy.escalate_exposed_to_block', false);
        config()->set('schemaguard.policy.block_confidence_floor', 'high');
        config()->set('schemaguard.policy.modes', [
            'column_dropped' => 'block',
            'column_renamed' => 'block',
            'table_dropped' => 'block',
            'column_type_changed' => 'warn',
        ]);
        config()->set('schemaguard.exit_codes.warning_exit_code', 0);
        config()->set('schemaguard.exit_codes.treat_warnings_as_failure', false);

        $this->forgetPhaseFiveInstances();
    }

    private function fixturesPath(): string
    {
        return realpath(__DIR__ . '/../Fixtures') ?: __DIR__ . '/../Fixtures';
    }

    private function migrationsPath(): string
    {
        return $this->fixturesPath() . DIRECTORY_SEPARATOR . 'migrations';
    }

    private function migration(string $name): string
    {
        return $this->migrationsPath() . DIRECTORY_SEPARATOR . $name;
    }

    private function fixture(string $path): string
    {
        return $this->fixturesPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function forgetPhaseFiveInstances(): void
    {
        foreach ([
            PolicyConfiguration::class,
            MigrationDiscovery::class,
            AnalysisPipeline::class,
            ExitCodeResolver::class,
            CheckCommand::class,
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function assertStrictJsonDocument(string $output): array
    {
        $trimmed = trim($output);

        $this->assertStringStartsWith('{', $trimmed);
        $this->assertStringEndsWith('}', $trimmed);
        $this->assertStringNotContainsString('Deployment Firewall', $output);
        $this->assertStringNotContainsString('Indexing source files', $output);
        $this->assertStringNotContainsString('RESULT:', $output);
        $this->assertStringNotContainsString('Surface | Location | Line | Confidence', $output);
        $this->assertDoesNotMatchRegularExpression('/\x1B\[[0-?]*[ -\/]*[@-~]/', $output);

        return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }
}
