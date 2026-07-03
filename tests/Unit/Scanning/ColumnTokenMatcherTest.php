<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use SchemaGuard\Scanning\ColumnTokenMatcher;
use SchemaGuard\Tests\TestCase;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\Rarity;

final class ColumnTokenMatcherTest extends TestCase
{
    public function test_it_classifies_common_and_rare_columns(): void
    {
        $matcher = new ColumnTokenMatcher(['phone']);

        $this->assertSame(Rarity::COMMON, $matcher->rarity('phone'));
        $this->assertSame(Confidence::LOW, $matcher->confidenceForUnresolved('phone'));

        $this->assertSame(Rarity::RARE, $matcher->rarity('stripe_customer_id'));
        $this->assertSame(Confidence::MEDIUM, $matcher->confidenceForUnresolved('stripe_customer_id'));
    }

    public function test_it_matches_sql_tokens_with_identifier_safe_boundaries(): void
    {
        $matcher = new ColumnTokenMatcher();

        $this->assertTrue($matcher->matchesInSql('SELECT phone FROM users', 'phone'));
        $this->assertTrue($matcher->matchesInSql('SELECT users.phone FROM users', 'phone'));
        $this->assertFalse($matcher->matchesInSql('SELECT telephone FROM users', 'phone'));
        $this->assertFalse($matcher->matchesInSql('SELECT phone_number FROM users', 'phone'));
    }
}
