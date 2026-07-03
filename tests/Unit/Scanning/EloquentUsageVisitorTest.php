<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use SchemaGuard\Scanning\ColumnTokenMatcher;
use SchemaGuard\Scanning\LocalTypeResolver;
use SchemaGuard\Scanning\Visitors\EloquentUsageVisitor;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SurfaceType;

final class EloquentUsageVisitorTest extends ScanningTestCase
{
    public function test_it_finds_static_model_query_column_usage(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['Http/Controllers/UserController.php'])[$this->fixture('Http/Controllers/UserController.php')];
        $usages = $this->runVisitor(
            new EloquentUsageVisitor($map, new LocalTypeResolver(), new ColumnTokenMatcher()),
            $file,
            'users.phone',
        );

        $details = array_map(static fn ($usage): string => $usage->detail, $usages);

        $this->assertContains('where()', $details);
        $this->assertContains('select()', $details);
        $this->assertContains('orderBy()', $details);
        $this->assertTrue($this->hasUsage($usages, SurfaceType::ELOQUENT_QUERY, Confidence::DEFINITIVE, 'where()'));
        $this->assertTrue($this->hasUsage($usages, SurfaceType::ELOQUENT_QUERY, Confidence::DEFINITIVE, 'select()'));
        $this->assertTrue($this->hasUsage($usages, SurfaceType::ELOQUENT_QUERY, Confidence::DEFINITIVE, 'orderBy()'));
    }

    public function test_it_resolves_typed_model_property_access(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['Http/Controllers/UserController.php'])[$this->fixture('Http/Controllers/UserController.php')];
        $usages = $this->runVisitor(
            new EloquentUsageVisitor($map, new LocalTypeResolver(), new ColumnTokenMatcher()),
            $file,
            'users.phone',
        );

        $this->assertTrue($this->hasUsage($usages, SurfaceType::ELOQUENT_QUERY, Confidence::DEFINITIVE, 'property access'));
    }

    public function test_unresolved_property_access_uses_rarity_confidence(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['unresolved_property.php'])[$this->fixture('unresolved_property.php')];
        $visitor = new EloquentUsageVisitor($map, new LocalTypeResolver(), new ColumnTokenMatcher(['phone']));

        $phoneUsages = $this->runVisitor($visitor, $file, 'users.phone');
        $this->assertCount(1, $phoneUsages);
        $this->assertSame(Confidence::LOW, $phoneUsages[0]->confidence);

        $rareUsages = $this->runVisitor($visitor, $file, 'users.phone_verified_at');
        $this->assertCount(1, $rareUsages);
        $this->assertSame(Confidence::MEDIUM, $rareUsages[0]->confidence);
    }

    /**
     * @param array<int, \SchemaGuard\ValueObjects\Usage> $usages
     */
    private function hasUsage(array $usages, SurfaceType $surface, Confidence $confidence, string $detail): bool
    {
        foreach ($usages as $usage) {
            if ($usage->surface === $surface && $usage->confidence === $confidence && $usage->detail === $detail) {
                return true;
            }
        }

        return false;
    }
}
