<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\ValueObjects;

use PHPUnit\Framework\TestCase;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\TableReference;

final class ReferenceTest extends TestCase
{
    public function test_table_reference_has_stable_id(): void
    {
        $this->assertSame('table:users', (new TableReference('users'))->id());
    }

    public function test_column_reference_has_stable_id_and_semantic_equality(): void
    {
        $column = new ColumnReference('users', 'phone');

        $this->assertSame('column:users.phone', $column->id());
        $this->assertTrue($column->equals(new ColumnReference('users', 'phone')));
        $this->assertFalse($column->equals(new ColumnReference('users', 'email')));
        $this->assertFalse($column->equals(new ColumnReference('drivers', 'phone')));
    }
}
