<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Migrations;

use Illuminate\Filesystem\Filesystem;
use SchemaGuard\Migrations\MigrationParser;
use SchemaGuard\Tests\TestCase;
use SchemaGuard\ValueObjects\ChangeType;

final class MigrationParserTest extends TestCase
{
    private MigrationParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new MigrationParser(new Filesystem());
    }

    public function test_it_parses_dropped_column_from_up_method_only(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_01_000000_drop_phone_from_users.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::COLUMN_DROPPED, $events[0]->type);
        $this->assertSame('users', $events[0]->column?->table);
        $this->assertSame('phone', $events[0]->column?->column);
        $this->assertSame($this->fixture('2024_06_01_000000_drop_phone_from_users.php'), $events[0]->location->file);
        $this->assertGreaterThan(0, $events[0]->location->line);
        $this->assertFalse($events[0]->indeterminate);
    }

    public function test_it_parses_renamed_column(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_02_000000_rename_users_column.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::COLUMN_RENAMED, $events[0]->type);
        $this->assertSame('users', $events[0]->column?->table);
        $this->assertSame('full_name', $events[0]->column?->column);
        $this->assertSame('name', $events[0]->renamedTo);
        $this->assertFalse($events[0]->indeterminate);
    }

    public function test_it_parses_dropped_table(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_03_000000_drop_legacy_logs_table.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::TABLE_DROPPED, $events[0]->type);
        $this->assertSame('legacy_logs', $events[0]->table?->table);
        $this->assertNull($events[0]->column);
        $this->assertFalse($events[0]->indeterminate);
    }

    public function test_it_parses_direct_dropped_table(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_04_000000_drop_legacy_logs_table_direct.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::TABLE_DROPPED, $events[0]->type);
        $this->assertSame('legacy_logs', $events[0]->table?->table);
        $this->assertNull($events[0]->column);
        $this->assertFalse($events[0]->indeterminate);
    }

    public function test_it_parses_multiple_dropped_columns_from_array_argument(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_05_000000_drop_multi_columns.php'));

        $this->assertCount(2, $events);
        $this->assertSame(['street', 'zip'], array_map(
            static fn ($event): string => $event->column->column,
            $events,
        ));
        $this->assertSame(['users', 'users'], array_map(
            static fn ($event): string => $event->column->table,
            $events,
        ));
    }

    public function test_it_records_dynamic_drop_column_as_indeterminate(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_06_000000_dynamic_drop.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::COLUMN_DROPPED, $events[0]->type);
        $this->assertSame('users', $events[0]->table?->table);
        $this->assertNull($events[0]->column);
        $this->assertTrue($events[0]->indeterminate);
        $this->assertSame('dynamic column name', $events[0]->reason);
    }

    public function test_it_does_not_silently_skip_dynamic_items_in_drop_column_arrays(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_09_000000_drop_mixed_dynamic_columns.php'));

        $this->assertCount(2, $events);
        $this->assertSame('street', $events[0]->column?->column);
        $this->assertFalse($events[0]->indeterminate);
        $this->assertNull($events[1]->column);
        $this->assertTrue($events[1]->indeterminate);
        $this->assertSame('dynamic column name', $events[1]->reason);
    }

    public function test_parse_many_merges_events_in_order(): void
    {
        $events = $this->parser->parseMany([
            $this->fixture('2024_06_01_000000_drop_phone_from_users.php'),
            $this->fixture('2024_06_03_000000_drop_legacy_logs_table.php'),
        ]);

        $this->assertCount(2, $events);
        $this->assertSame(ChangeType::COLUMN_DROPPED, $events[0]->type);
        $this->assertSame(ChangeType::TABLE_DROPPED, $events[1]->type);
    }

    public function test_it_ignores_destructive_calls_in_down_method(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_07_000000_down_method_destructive_call.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::COLUMN_DROPPED, $events[0]->type);
        $this->assertSame('phone', $events[0]->column?->column);
    }

    public function test_it_ignores_strings_comments_non_destructive_and_column_add_operations(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_08_000000_non_destructive_changes.php'));

        $this->assertSame([], $events);
    }

    public function test_it_parses_column_type_changes_with_change_modifier(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_10_000000_change_user_email_type.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::COLUMN_TYPE_CHANGED, $events[0]->type);
        $this->assertSame('users', $events[0]->column?->table);
        $this->assertSame('email', $events[0]->column?->column);
        $this->assertSame('string', $events[0]->newType);
        $this->assertFalse($events[0]->indeterminate);
    }

    public function test_it_parses_direct_column_type_changes_with_change_modifier(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_14_000000_change_user_email_type_direct.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::COLUMN_TYPE_CHANGED, $events[0]->type);
        $this->assertSame('users', $events[0]->column?->table);
        $this->assertSame('email', $events[0]->column?->column);
        $this->assertSame('string', $events[0]->newType);
        $this->assertFalse($events[0]->indeterminate);
    }

    public function test_it_supports_custom_blueprint_variables_and_does_not_leak_table_context(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_11_000000_custom_blueprint_and_multiple_tables.php'));

        $this->assertCount(2, $events);
        $this->assertSame(['users.phone', 'posts.title'], array_map(
            static fn ($event): string => $event->column->table . '.' . $event->column->column,
            $events,
        ));
    }

    public function test_multi_table_closures_keep_table_context_isolated(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_17_000000_drop_multi_table_columns.php'));

        $this->assertCount(2, $events);
        $this->assertSame(['users.phone', 'orders.legacy_code'], array_map(
            static fn ($event): string => $event->column->table . '.' . $event->column->column,
            $events,
        ));
    }

    public function test_drop_readded_in_same_up_migration_is_marked_neutralized(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_18_000000_drop_and_readd_user_phone.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::COLUMN_DROPPED, $events[0]->type);
        $this->assertSame('users', $events[0]->column?->table);
        $this->assertSame('phone', $events[0]->column?->column);
        $this->assertTrue($events[0]->neutralized);
        $this->assertStringContainsString('neutralized', $events[0]->reason ?? '');
    }

    public function test_unrelated_readded_column_does_not_neutralize_drop(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_19_000000_drop_phone_readd_email.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::COLUMN_DROPPED, $events[0]->type);
        $this->assertSame('users', $events[0]->column?->table);
        $this->assertSame('phone', $events[0]->column?->column);
        $this->assertFalse($events[0]->neutralized);
        $this->assertNull($events[0]->reason);
    }

    public function test_readd_on_another_table_does_not_neutralize_drop(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_20_000000_drop_user_phone_readd_order_phone.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::COLUMN_DROPPED, $events[0]->type);
        $this->assertSame('users', $events[0]->column?->table);
        $this->assertSame('phone', $events[0]->column?->column);
        $this->assertFalse($events[0]->neutralized);
        $this->assertNull($events[0]->reason);
    }

    public function test_dynamic_rename_arguments_are_indeterminate(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_12_000000_dynamic_rename.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::COLUMN_RENAMED, $events[0]->type);
        $this->assertSame('users', $events[0]->table?->table);
        $this->assertNull($events[0]->column);
        $this->assertTrue($events[0]->indeterminate);
        $this->assertSame('dynamic old column name', $events[0]->reason);
    }

    public function test_dynamic_table_drop_is_indeterminate(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_13_000000_dynamic_table_drop.php'));

        $this->assertCount(1, $events);
        $this->assertSame(ChangeType::TABLE_DROPPED, $events[0]->type);
        $this->assertNull($events[0]->table);
        $this->assertTrue($events[0]->indeterminate);
        $this->assertSame('dynamic table name', $events[0]->reason);
    }

    public function test_malformed_migration_degrades_to_empty_result_and_records_diagnostic(): void
    {
        $events = $this->parser->parseFile(__DIR__ . '/../../Fixtures/malformed/broken_migration.fixture');

        $this->assertSame([], $events);
        $this->assertCount(1, $this->parser->diagnostics());
        $this->assertStringContainsString('Could not parse migration', $this->parser->diagnostics()[0]);
    }

    public function test_missing_migration_degrades_to_empty_result_and_records_diagnostic(): void
    {
        $events = $this->parser->parseFile($this->fixture('missing.php'));

        $this->assertSame([], $events);
        $this->assertCount(1, $this->parser->diagnostics());
        $this->assertStringContainsString('Migration file not found', $this->parser->diagnostics()[0]);
    }

    public function test_parse_file_diagnostics_do_not_leak_between_independent_runs(): void
    {
        $this->parser->parseFile(__DIR__ . '/../../Fixtures/malformed/broken_migration.fixture');
        $this->assertNotSame([], $this->parser->diagnostics());

        $events = $this->parser->parseFile($this->fixture('2024_06_01_000000_drop_phone_from_users.php'));

        $this->assertCount(1, $events);
        $this->assertSame([], $this->parser->diagnostics());
    }

    public function test_parse_many_accumulates_diagnostics_across_a_batch(): void
    {
        $events = $this->parser->parseMany([
            __DIR__ . '/../../Fixtures/malformed/broken_migration.fixture',
            $this->fixture('missing.php'),
            $this->fixture('2024_06_01_000000_drop_phone_from_users.php'),
        ]);

        $this->assertCount(1, $events);
        $this->assertCount(2, $this->parser->diagnostics());
        $this->assertStringContainsString('Could not parse migration', $this->parser->diagnostics()[0]);
        $this->assertStringContainsString('Migration file not found', $this->parser->diagnostics()[1]);
    }

    private function fixture(string $name): string
    {
        return __DIR__ . '/../../Fixtures/migrations/' . $name;
    }
}
