<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use Illuminate\Filesystem\Filesystem;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Class_;
use SchemaGuard\Scanning\CodebaseIndexer;
use SchemaGuard\Tests\TestCase;

final class CodebaseIndexerTest extends TestCase
{
    public function test_it_indexes_php_files_recursively_and_skips_ignored_paths(): void
    {
        $index = $this->indexer()->index([$this->fixturesPath()]);

        $this->assertArrayHasKey($this->fixture('Models/User.php'), $index);
        $this->assertArrayHasKey($this->fixture('Http/Resources/UserResource.php'), $index);
        $this->assertArrayNotHasKey($this->fixture('Ignored/Ignored.php'), $index);
    }

    public function test_duplicate_scan_paths_do_not_duplicate_index_entries_within_one_run(): void
    {
        $index = $this->indexer()->index([
            $this->fixture('Models/User.php'),
            $this->fixture('Models/User.php'),
        ]);

        $this->assertCount(1, array_keys($index, $index[$this->fixture('Models/User.php')], true));
        $this->assertCount(1, $index);
    }

    public function test_it_marks_valid_files_as_parsed_and_broken_files_as_failed(): void
    {
        $index = $this->indexer()->index([$this->fixturesPath()]);

        $this->assertTrue($index[$this->fixture('Models/User.php')]->parsed);
        $this->assertNotNull($index[$this->fixture('Models/User.php')]->ast);
        $this->assertNull($index[$this->fixture('Models/User.php')]->error);

        $this->assertFalse($index[$this->fixture('broken_syntax.php')]->parsed);
        $this->assertNull($index[$this->fixture('broken_syntax.php')]->ast);
        $this->assertIsString($index[$this->fixture('broken_syntax.php')]->error);
    }

    public function test_a_broken_file_does_not_abort_indexing_of_valid_files(): void
    {
        $index = $this->indexer()->index([$this->fixturesPath()]);

        $this->assertFalse($index[$this->fixture('broken_syntax.php')]->parsed);
        $this->assertTrue($index[$this->fixture('Http/Controllers/UserController.php')]->parsed);
        $this->assertTrue($index[$this->fixture('Models/Post.php')]->parsed);
    }

    public function test_indexed_ast_contains_resolved_names_and_parent_connections(): void
    {
        $parsedFile = $this->indexer()->index([$this->fixture('Models/User.php')])[$this->fixture('Models/User.php')];
        $nodes = $this->flatten($parsedFile->ast ?? []);

        $class = $this->first($nodes, static fn (Node $node): bool => $node instanceof Class_);
        $this->assertInstanceOf(Class_::class, $class);
        $this->assertSame('SchemaGuard\Tests\Fixtures\Models\User', $class->namespacedName?->toString());

        $methodCall = $this->first($nodes, static fn (Node $node): bool => $node instanceof MethodCall);
        $this->assertInstanceOf(MethodCall::class, $methodCall);
        $this->assertInstanceOf(Node::class, $methodCall->getAttribute('parent'));
    }

    private function indexer(): CodebaseIndexer
    {
        return new CodebaseIndexer(new Filesystem(), [
            'ignore_paths' => [
                '*/Ignored/*',
            ],
        ]);
    }

    private function fixturesPath(): string
    {
        return realpath(__DIR__ . '/../../Fixtures') ?: __DIR__ . '/../../Fixtures';
    }

    private function fixture(string $path): string
    {
        $fullPath = $this->fixturesPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

        return realpath($fullPath) ?: $fullPath;
    }

    /**
     * @param array<int, Node> $nodes
     *
     * @return array<int, Node>
     */
    private function flatten(array $nodes): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $flat[] = $node;

            foreach ($node->getSubNodeNames() as $name) {
                $child = $node->{$name};

                if ($child instanceof Node) {
                    $flat = array_merge($flat, $this->flatten([$child]));
                } elseif (is_array($child)) {
                    $flat = array_merge($flat, $this->flatten(array_filter(
                        $child,
                        static fn (mixed $item): bool => $item instanceof Node,
                    )));
                }
            }
        }

        return $flat;
    }

    /**
     * @param array<int, Node> $nodes
     * @param callable(Node): bool $predicate
     */
    private function first(array $nodes, callable $predicate): ?Node
    {
        foreach ($nodes as $node) {
            if ($predicate($node)) {
                return $node;
            }
        }

        return null;
    }
}
