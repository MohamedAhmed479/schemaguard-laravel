<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Migrations;

use BadMethodCallException;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use SchemaGuard\Migrations\MigrationDiscovery;
use SchemaGuard\Tests\TestCase;

final class MigrationDiscoveryTest extends TestCase
{
    public function test_explicit_migration_paths_are_validated_and_sorted(): void
    {
        $discovery = new MigrationDiscovery(new Filesystem());

        $paths = [
            $this->fixture('2024_06_06_000000_dynamic_drop.php'),
            $this->fixture('2024_06_01_000000_drop_phone_from_users.php'),
            $this->fixture('2024_06_05_000000_drop_multi_columns.php'),
        ];

        $this->assertSame([
            $this->fixture('2024_06_01_000000_drop_phone_from_users.php'),
            $this->fixture('2024_06_05_000000_drop_multi_columns.php'),
            $this->fixture('2024_06_06_000000_dynamic_drop.php'),
        ], $discovery->resolve(['migrations' => $paths]));
    }

    public function test_pending_migration_paths_are_loaded_from_config_and_sorted(): void
    {
        config()->set('schemaguard.migration_paths', [
            $this->fixturesPath(),
        ]);

        $discovery = $this->app->make(MigrationDiscovery::class);

        $this->assertSame([
            $this->fixture('2024_06_01_000000_drop_phone_from_users.php'),
            $this->fixture('2024_06_02_000000_rename_users_column.php'),
            $this->fixture('2024_06_03_000000_drop_legacy_logs_table.php'),
            $this->fixture('2024_06_04_000000_drop_legacy_logs_table_direct.php'),
            $this->fixture('2024_06_05_000000_drop_multi_columns.php'),
            $this->fixture('2024_06_06_000000_dynamic_drop.php'),
            $this->fixture('2024_06_07_000000_down_method_destructive_call.php'),
            $this->fixture('2024_06_08_000000_non_destructive_changes.php'),
            $this->fixture('2024_06_09_000000_drop_mixed_dynamic_columns.php'),
            $this->fixture('2024_06_10_000000_change_user_email_type.php'),
            $this->fixture('2024_06_11_000000_custom_blueprint_and_multiple_tables.php'),
            $this->fixture('2024_06_12_000000_dynamic_rename.php'),
            $this->fixture('2024_06_13_000000_dynamic_table_drop.php'),
            $this->fixture('2024_06_14_000000_change_user_email_type_direct.php'),
        ], $discovery->resolve());
    }

    public function test_pending_discovery_ignores_non_php_files(): void
    {
        config()->set('schemaguard.migration_paths', [
            $this->fixturesPath(),
        ]);

        $discovery = $this->app->make(MigrationDiscovery::class);

        $this->assertNotContains($this->fixture('not_a_migration.txt'), $discovery->resolve());
    }

    public function test_explicit_missing_file_throws(): void
    {
        $discovery = new MigrationDiscovery(new Filesystem());

        $this->expectException(InvalidArgumentException::class);

        $discovery->resolve([
            'migrations' => [
                $this->fixture('missing.php'),
            ],
        ]);
    }

    public function test_explicit_non_php_file_throws(): void
    {
        $discovery = new MigrationDiscovery(new Filesystem());

        $this->expectException(InvalidArgumentException::class);

        $discovery->resolve([
            'migrations' => [
                $this->fixture('not_a_migration.txt'),
            ],
        ]);
    }

    public function test_git_diff_strategy_is_not_supported_until_phase_five(): void
    {
        $discovery = new MigrationDiscovery(new Filesystem());

        $this->expectException(BadMethodCallException::class);

        $discovery->resolve(['strategy' => 'git_diff']);
    }

    private function fixture(string $name): string
    {
        return $this->fixturesPath() . DIRECTORY_SEPARATOR . $name;
    }

    private function fixturesPath(): string
    {
        return realpath(__DIR__ . '/../../Fixtures/migrations') ?: __DIR__ . '/../../Fixtures/migrations';
    }
}
