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

    public function test_it_parses_multiple_dropped_columns_from_array_argument(): void
    {
        $events = $this->parser->parseFile($this->fixture('2024_06_05_000000_drop_multi_columns.php'));

        $this->assertCount(2, $events);
        $this->assertSame(['street', 'zip'], array_map(
            static fn ($event): string => $event->column->column,
            $events,
        ));
        $this->assertSame(['addresses', 'addresses'], array_map(
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

    public function test_malformed_migration_degrades_to_empty_result_and_records_diagnostic(): void
    {
        $events = $this->parser->parseFile(__DIR__ . '/../../Fixtures/malformed/broken_migration.fixture');

        $this->assertSame([], $events);
        $this->assertCount(1, $this->parser->diagnostics());
        $this->assertStringContainsString('Could not parse migration', $this->parser->diagnostics()[0]);
    }

    private function fixture(string $name): string
    {
        return __DIR__ . '/../../Fixtures/migrations/' . $name;
    }
}
