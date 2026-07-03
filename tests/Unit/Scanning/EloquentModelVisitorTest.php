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

    public function test_it_recognizes_safe_model_inheritance_and_structural_fallbacks(): void
    {
        $map = $this->modelTableMap();

        $this->assertSame('auth_users', $map->tableForModel('SchemaGuard\Tests\Fixtures\Models\AuthUser'));
        $this->assertSame('memberships', $map->tableForModel('SchemaGuard\Tests\Fixtures\Models\Membership'));
        $this->assertSame('invoices', $map->tableForModel('SchemaGuard\Tests\Fixtures\Models\Invoice'));
        $this->assertSame('structural_models', $map->tableForModel('SchemaGuard\Tests\Fixtures\Models\StructuralModel'));
        $this->assertSame('relation_backed_models', $map->tableForModel('SchemaGuard\Tests\Fixtures\Models\TableRelationModel'));
        $this->assertNull($map->tableForModel('SchemaGuard\Tests\Fixtures\Models\TableOnlyDto'));
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

    public function test_appends_only_virtual_attribute_does_not_create_fake_backing_column_usage(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['Models/AppendedOnlyProfile.php'])[$this->fixture('Models/AppendedOnlyProfile.php')];

        $this->assertSame([], $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'profiles.display_name'));
    }

    public function test_modern_accessor_with_real_backing_column_is_recognized(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['Models/Profile.php'])[$this->fixture('Models/Profile.php')];
        $usages = $this->runVisitor(EloquentModelVisitor::usage($map), $file, 'profiles.display_name');

        $this->assertTrue($this->hasDetail($usages, '$fillable'));
        $this->assertTrue($this->hasDetail($usages, 'displayName'));
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

    public function test_it_finds_complex_relationship_key_columns(): void
    {
        $map = $this->modelTableMap();

        $userFile = $this->index(['Models/User.php'])[$this->fixture('Models/User.php')];
        $postFile = $this->index(['Models/Post.php'])[$this->fixture('Models/Post.php')];
        $countryFile = $this->index(['Models/Country.php'])[$this->fixture('Models/Country.php')];
        $imageFile = $this->index(['Models/Image.php'])[$this->fixture('Models/Image.php')];

        $this->assertRelationUsage($this->runVisitor(EloquentModelVisitor::usage($map), $postFile, 'posts.user_id'), 'belongsTo');
        $this->assertRelationUsage($this->runVisitor(EloquentModelVisitor::usage($map), $postFile, 'users.id'), 'belongsTo');
        $this->assertRelationUsage($this->runVisitor(EloquentModelVisitor::usage($map), $userFile, 'role_user.user_id'), 'belongsToMany');
        $this->assertRelationUsage($this->runVisitor(EloquentModelVisitor::usage($map), $userFile, 'role_user.role_id'), 'belongsToMany');
        $this->assertRelationUsage($this->runVisitor(EloquentModelVisitor::usage($map), $countryFile, 'users.country_id'), 'hasManyThrough');
        $this->assertRelationUsage($this->runVisitor(EloquentModelVisitor::usage($map), $countryFile, 'profiles.user_id'), 'hasOneThrough');
        $this->assertRelationUsage($this->runVisitor(EloquentModelVisitor::usage($map), $userFile, 'images.imageable_type'), 'morphMany');
        $this->assertRelationUsage($this->runVisitor(EloquentModelVisitor::usage($map), $userFile, 'images.imageable_id'), 'morphMany');
        $this->assertRelationUsage($this->runVisitor(EloquentModelVisitor::usage($map), $imageFile, 'images.imageable_type'), 'morphTo');
        $this->assertRelationUsage($this->runVisitor(EloquentModelVisitor::usage($map), $imageFile, 'images.imageable_id'), 'morphTo');
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

    /**
     * @param array<int, \SchemaGuard\ValueObjects\Usage> $usages
     */
    private function assertRelationUsage(array $usages, string $detail): void
    {
        foreach ($usages as $usage) {
            if (
                $usage->surface === SurfaceType::RELATION
                && $usage->confidence === Confidence::DEFINITIVE
                && $usage->detail === $detail
            ) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        $this->fail("Failed asserting that relation usage with detail {$detail} exists.");
    }
}
