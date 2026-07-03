<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use Illuminate\Filesystem\Filesystem;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use SchemaGuard\Scanning\AstCache;
use SchemaGuard\Scanning\CodebaseIndexer;
use SchemaGuard\Tests\TestCase;

final class AstCacheTest extends TestCase
{
    private Filesystem $files;

    private string $workspace;

    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->workspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'schemaguard-ast-cache-' . bin2hex(random_bytes(6));
        $this->cachePath = $this->workspace . DIRECTORY_SEPARATOR . 'cache';
        $this->files->ensureDirectoryExists($this->workspace);
    }

    protected function tearDown(): void
    {
        if (isset($this->workspace) && $this->files->isDirectory($this->workspace)) {
            $this->files->deleteDirectory($this->workspace);
        }

        parent::tearDown();
    }

    public function test_first_parse_is_a_miss_then_stores_and_hits_cache(): void
    {
        [$path, $source] = $this->sourceFile('<?php $user->phone();');
        $cache = new AstCache($this->files, $this->cachePath);

        $this->assertNull($cache->get($path, $source));

        $cache->put($path, $source, $this->astFor($source));

        $this->assertNotSame([], $this->cacheFiles());
        $this->assertIsArray($cache->get($path, $source));
    }

    public function test_content_modification_invalidates_the_cache(): void
    {
        [$path, $source] = $this->sourceFile('<?php $user->phone();');
        $cache = new AstCache($this->files, $this->cachePath);
        $cache->put($path, $source, $this->astFor($source));

        $modified = '<?php $user->email();';

        $this->assertNull($cache->get($path, $modified));
    }

    public function test_mtime_only_change_invalidates_the_cache(): void
    {
        [$path, $source] = $this->sourceFile('<?php $user->phone();');
        $cache = new AstCache($this->files, $this->cachePath);
        $cache->put($path, $source, $this->astFor($source));

        $this->assertIsArray($cache->get($path, $source));

        touch($path, (time() + 10));
        clearstatcache(true, $path);

        $this->assertNull($cache->get($path, $source));
    }

    public function test_no_cache_bypasses_reads_and_writes(): void
    {
        [$path, $source] = $this->sourceFile('<?php $user->phone();');
        $cache = new AstCache($this->files, $this->cachePath, enabled: false);

        $cache->put($path, $source, $this->astFor($source));

        $this->assertSame([], $this->cacheFiles());
        $this->assertNull($cache->get($path, $source));
    }

    public function test_corrupted_cache_degrades_to_a_fresh_parse_path(): void
    {
        [$path, $source] = $this->sourceFile('<?php $user->phone();');
        $cache = new AstCache($this->files, $this->cachePath);
        $cache->put($path, $source, $this->astFor($source));

        $this->files->put($this->cacheFiles()[0], 'corrupted-payload');

        $this->assertNull($cache->get($path, $source));
    }

    public function test_indexer_prepares_cached_ast_with_parent_links(): void
    {
        [$path] = $this->sourceFile('<?php $user->phone();');
        $indexer = new CodebaseIndexer(
            $this->files,
            ['ignore_paths' => []],
            new AstCache($this->files, $this->cachePath),
        );

        $indexer->index([$path]);
        $index = $indexer->index([$path]);
        $methodCall = (new NodeFinder())->findFirstInstanceOf($index[$path]->ast ?? [], MethodCall::class);

        $this->assertInstanceOf(MethodCall::class, $methodCall);
        $this->assertInstanceOf(Node::class, $methodCall->getAttribute('parent'));
    }

    public function test_indexer_no_cache_bypasses_cache_writes(): void
    {
        [$path] = $this->sourceFile('<?php $user->phone();');
        $indexer = new CodebaseIndexer(
            $this->files,
            ['ignore_paths' => []],
            new AstCache($this->files, $this->cachePath),
        );

        $indexer->index([$path], useCache: false);

        $this->assertSame([], $this->cacheFiles());
    }

    /**
     * @return array{0:string,1:string}
     */
    private function sourceFile(string $source): array
    {
        $path = $this->workspace . DIRECTORY_SEPARATOR . 'Sample.php';
        $this->files->put($path, $source);
        clearstatcache(true, $path);

        return [$path, $source];
    }

    /**
     * @return array<int, \PhpParser\Node>
     */
    private function astFor(string $source): array
    {
        return (new ParserFactory())->createForNewestSupportedVersion()->parse($source) ?? [];
    }

    /**
     * @return string[]
     */
    private function cacheFiles(): array
    {
        if (! $this->files->isDirectory($this->cachePath)) {
            return [];
        }

        $files = glob($this->cachePath . DIRECTORY_SEPARATOR . '*.cache') ?: [];
        sort($files);

        return $files;
    }
}
