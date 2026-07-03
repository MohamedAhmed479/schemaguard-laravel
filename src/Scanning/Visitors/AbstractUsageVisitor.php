<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning\Visitors;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use SchemaGuard\Scanning\ParsedFile;
use SchemaGuard\ValueObjects\SourceLocation;
use SchemaGuard\ValueObjects\SymbolTargetSet;
use SchemaGuard\ValueObjects\Usage;

abstract class AbstractUsageVisitor extends NodeVisitorAbstract
{
    protected ?ParsedFile $file = null;

    protected ?SymbolTargetSet $targets = null;

    /** @var Usage[] */
    private array $usages = [];

    public function reset(ParsedFile $file, SymbolTargetSet $targets): void
    {
        $this->file = $file;
        $this->targets = $targets;
        $this->usages = [];
    }

    /**
     * @return Usage[]
     */
    public function usages(): array
    {
        return $this->usages;
    }

    protected function emit(Usage $usage): void
    {
        $this->usages[] = $usage;
    }

    protected function location(Node $node): SourceLocation
    {
        return SourceLocation::fromNode($this->file?->path ?? '', $node);
    }

    protected function resolveName(Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        return ltrim(($resolved instanceof Name ? $resolved : $name)->toString(), '\\');
    }

    protected function enclosingClass(Node $node): ?Class_
    {
        $cursor = $node;

        while (($parent = $cursor->getAttribute('parent')) instanceof Node) {
            if ($parent instanceof Class_) {
                return $parent;
            }

            $cursor = $parent;
        }

        return null;
    }

    protected function enclosingMethod(Node $node): ?ClassMethod
    {
        $cursor = $node;

        while (($parent = $cursor->getAttribute('parent')) instanceof Node) {
            if ($parent instanceof ClassMethod) {
                return $parent;
            }

            $cursor = $parent;
        }

        return null;
    }

    protected function classFqcn(Class_ $class): ?string
    {
        return isset($class->namespacedName) ? $class->namespacedName->toString() : null;
    }
}
