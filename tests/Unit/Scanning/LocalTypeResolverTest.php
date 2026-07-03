<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use SchemaGuard\Scanning\LocalTypeResolver;

final class LocalTypeResolverTest extends ScanningTestCase
{
    public function test_it_resolves_supported_local_types_and_unknowns(): void
    {
        $file = $this->index(['type_resolver.php'])[$this->fixture('type_resolver.php')];
        $function = (new NodeFinder())->findFirstInstanceOf($file->ast ?? [], Function_::class);
        $map = $this->modelTableMap();
        $resolver = new LocalTypeResolver();

        $this->assertInstanceOf(Function_::class, $function);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\User', $resolver->resolveVariable('user', $function, $map)->name);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\User', $resolver->resolveVariable('docUser', $function, $map)->name);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\User', $resolver->resolveVariable('newUser', $function, $map)->name);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\User', $resolver->resolveVariable('foundUser', $function, $map)->name);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\User', $resolver->resolveVariable('queryUser', $function, $map)->name);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\User', $resolver->resolveVariable('chainedUser', $function, $map)->name);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\User', $resolver->resolveVariable('whereUser', $function, $map)->name);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\User', $resolver->resolveVariable('createdUser', $function, $map)->name);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\User', $resolver->resolveVariable('firstUser', $function, $map)->name);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\User', $resolver->resolveVariable('firstOrFailUser', $function, $map)->name);

        $query = $resolver->resolveVariable('query', $function, $map);
        $this->assertTrue($query->isTable());
        $this->assertSame('users', $query->name);

        $chainedQuery = $resolver->resolveVariable('chainedQuery', $function, $map);
        $this->assertTrue($chainedQuery->isTable());
        $this->assertSame('users', $chainedQuery->name);

        $this->assertTrue($resolver->resolveVariable('nonModelId', $function, $map)->isUnknown());
        $this->assertTrue($resolver->resolveVariable('notModel', $function, $map)->isUnknown());
        $this->assertTrue($resolver->resolveVariable('unknownNew', $function, $map)->isUnknown());
        $this->assertTrue($resolver->resolveVariable('unknown', $function, $map)->isUnknown());
    }

    public function test_it_resolves_relation_property_traversal_and_foreach_values(): void
    {
        $file = $this->index(['relation_type_resolver.php'])[$this->fixture('relation_type_resolver.php')];
        $function = (new NodeFinder())->findFirstInstanceOf($file->ast ?? [], Function_::class);
        $map = $this->modelTableMap();
        $resolver = new LocalTypeResolver();

        $this->assertInstanceOf(Function_::class, $function);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\Post', $resolver->resolveVariable('posts', $function, $map)->name);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\Post', $resolver->resolveVariable('post', $function, $map)->name);
    }
}
