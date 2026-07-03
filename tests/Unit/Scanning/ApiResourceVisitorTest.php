<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use SchemaGuard\Scanning\Visitors\ApiResourceVisitor;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SurfaceType;

final class ApiResourceVisitorTest extends ScanningTestCase
{
    public function test_it_finds_resource_attribute_exposure_when_model_association_is_proven(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['Http/Resources/UserResource.php'])[$this->fixture('Http/Resources/UserResource.php')];
        $usages = $this->runVisitor(new ApiResourceVisitor($map), $file, 'users.phone');

        $this->assertCount(1, $usages);
        $this->assertSame('users', $usages[0]->symbol->table);
        $this->assertSame('phone', $usages[0]->symbol->column);
        $this->assertSame(SurfaceType::API_RESOURCE, $usages[0]->surface);
        $this->assertSame(Confidence::DEFINITIVE, $usages[0]->confidence);
    }

    public function test_resource_fallback_is_high_not_definitive_when_model_association_is_not_proven(): void
    {
        $map = $this->modelTableMap();
        $file = $this->index(['Http/Resources/PhoneResource.php'])[$this->fixture('Http/Resources/PhoneResource.php')];
        $usages = $this->runVisitor(new ApiResourceVisitor($map), $file, 'users.phone');

        $this->assertCount(1, $usages);
        $this->assertSame(SurfaceType::API_RESOURCE, $usages[0]->surface);
        $this->assertSame(Confidence::HIGH, $usages[0]->confidence);
    }
}
