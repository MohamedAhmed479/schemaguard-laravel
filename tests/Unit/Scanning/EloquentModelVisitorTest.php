<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use SchemaGuard\Scanning\Visitors\EloquentModelVisitor;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SurfaceType;

final class EloquentModelVisitorTest extends ScanningTestCase
{
    public function test_it_finds_model_schema_columns_with_definitive_confidence(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['Models/User.php'])[$this->fixture('Models/User.php')];
        $usages = $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'users.phone');

        $this->assertNotSame([], $usages);
        $this->assertContains(SurfaceType::MODEL_SCHEMA, array_map(static fn ($usage) => $usage->surface, $usages));
        $this->assertContains(Confidence::DEFINITIVE, array_map(static fn ($usage) => $usage->confidence, $usages));
        $this->assertContains('$fillable', array_map(static fn ($usage) => $usage->detail, $usages));
    }

    public function test_model_table_map_uses_explicit_and_default_table_names_without_filename_only_registration(): void
    {
        $map = $this->modelTableMap();

        $this->assertSame('crm_accounts', $map->tableForModel('SchemaGuard\Tests\Fixtures\Models\Account'));
        $this->assertSame('blog_posts', $map->tableForModel('SchemaGuard\Tests\Fixtures\Models\BlogPost'));
        $this->assertNull($map->tableForModel('SchemaGuard\Tests\Fixtures\Models\FilenameOnly'));
    }

    public function test_it_handles_supported_model_schema_arrays_only_where_applicable(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['Models/User.php'])[$this->fixture('Models/User.php')];

        $this->assertTrue($this->hasDetail(
            $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'users.phone'),
            '$fillable',
        ));
        $this->assertTrue($this->hasDetail(
            $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'users.ssn'),
            '$guarded',
        ));
        $this->assertTrue($this->hasDetail(
            $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'users.phone_verified_at'),
            '$casts',
        ));
        $this->assertTrue($this->hasDetail(
            $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'users.password'),
            '$hidden',
        ));
        $this->assertTrue($this->hasDetail(
            $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'users.public_phone'),
            '$visible',
        ));
        $this->assertTrue($this->hasDetail(
            $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'users.deleted_at'),
            '$dates',
        ));
        $this->assertSame([], $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'users.full_name'));
    }

    public function test_it_covers_legacy_accessors_and_mutators(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['Models/User.php'])[$this->fixture('Models/User.php')];
        $details = array_map(
            static fn ($usage): string => $usage->detail,
            $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'users.phone'),
        );

        $this->assertContains('getPhoneAttribute', $details);
        $this->assertContains('setPhoneAttribute', $details);
    }

    public function test_computed_modern_attribute_accessor_does_not_create_fake_backing_column_usage(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['Models/User.php'])[$this->fixture('Models/User.php')];

        $this->assertSame([], $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'users.full_name'));
    }

    public function test_it_finds_relationship_key_columns(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['Models/User.php'])[$this->fixture('Models/User.php')];
        $usages = $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'posts.user_id');

        $this->assertCount(1, $usages);
        $this->assertSame(SurfaceType::RELATION, $usages[0]->surface);
        $this->assertSame(Confidence::DEFINITIVE, $usages[0]->confidence);
    }

    /**
     * @param array<int, \SchemaGuard\ValueObjects\Usage> $usages
     */
    private function hasDetail(array $usages, string $detail): bool
    {
        foreach ($usages as $usage) {
            if (
                $usage->surface === SurfaceType::MODEL_SCHEMA
                && $usage->confidence === Confidence::DEFINITIVE
                && $usage->detail === $detail
            ) {
                return true;
            }
        }

        return false;
    }
}
