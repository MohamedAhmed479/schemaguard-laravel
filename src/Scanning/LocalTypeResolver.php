<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;

final class LocalTypeResolver
{
    private const MODEL_ENTRYPOINTS = [
        'create',
        'find',
        'first',
        'firstOrFail',
        'query',
        'where',
    ];

    public function resolveVariable(Variable|string $variable, Node $context, ModelTableMap $modelTableMap): ResolvedType
    {
        $name = $variable instanceof Variable ? $variable->name : $variable;

        if (! is_string($name)) {
            return ResolvedType::unknown();
        }

        $scope = $this->scopeFor($context);

        if ($scope === null) {
            return ResolvedType::unknown();
        }

        return $this->symbolTable($scope, $modelTableMap)[$name] ?? ResolvedType::unknown();
    }

    public function resolveExpression(Expr $expr, Node $context, ModelTableMap $modelTableMap): ResolvedType
    {
        $scope = $this->scopeFor($context);
        $symbols = $scope === null ? [] : $this->symbolTable($scope, $modelTableMap);

        return $this->resolveExpressionWithSymbols($expr, $context, $modelTableMap, $symbols);
    }

    /**
     * @return array<string, ResolvedType>
     */
    private function symbolTable(ClassMethod|Function_|\PhpParser\Node\Expr\Closure $scope, ModelTableMap $modelTableMap): array
    {
        $symbols = [];

        foreach ($scope->params as $param) {
            if (! $param->var instanceof Variable || ! is_string($param->var->name) || $param->type === null) {
                continue;
            }

            $resolved = $this->resolveTypeNode($param->type, $scope, $modelTableMap);
            if (! $resolved->isUnknown()) {
                $symbols[$param->var->name] = $resolved;
            }
        }

        $finder = new NodeFinder();
        $nodes = $finder->find($scope->stmts ?? [], static fn (Node $node): bool => true);

        foreach ($nodes as $node) {
            $this->applyVarDocblock($node->getDocComment(), $node, $modelTableMap, $symbols);

            if ($node instanceof Assign && $node->var instanceof Variable && is_string($node->var->name)) {
                $resolved = $this->resolveExpressionWithSymbols($node->expr, $node, $modelTableMap, $symbols);
                if (! $resolved->isUnknown()) {
                    $symbols[$node->var->name] = $resolved;
                }
            }

            if ($node instanceof Foreach_ && $node->valueVar instanceof Variable && is_string($node->valueVar->name)) {
                $resolved = $this->resolveExpressionWithSymbols($node->expr, $node, $modelTableMap, $symbols);
                if ($resolved->isModel()) {
                    $symbols[$node->valueVar->name] = $resolved;
                }
            }
        }

        return $symbols;
    }

    /**
     * @param array<string, ResolvedType> $symbols
     */
    private function resolveExpressionWithSymbols(
        Expr $expr,
        Node $context,
        ModelTableMap $modelTableMap,
        array $symbols,
    ): ResolvedType {
        if ($expr instanceof Variable && is_string($expr->name)) {
            return $symbols[$expr->name] ?? ResolvedType::unknown();
        }

        if ($expr instanceof New_ && $expr->class instanceof Name) {
            return $this->modelTypeFromName($expr->class, $context, $modelTableMap);
        }

        if ($expr instanceof StaticCall) {
            return $this->resolveStaticCall($expr, $context, $modelTableMap);
        }

        if ($expr instanceof MethodCall) {
            if ($this->methodName($expr) === 'table' && $this->isDbFacade($expr->var) && ($expr->args[0]->value ?? null) instanceof String_) {
                return ResolvedType::table($expr->args[0]->value->value);
            }

            if ($expr->var instanceof StaticCall) {
                return $this->resolveStaticCall($expr->var, $context, $modelTableMap);
            }

            if ($expr->var instanceof MethodCall) {
                return $this->resolveExpressionWithSymbols($expr->var, $context, $modelTableMap, $symbols);
            }
        }

        if ($expr instanceof PropertyFetch && $expr->name instanceof Identifier) {
            $receiver = $this->resolveExpressionWithSymbols($expr->var, $context, $modelTableMap, $symbols);
            if ($receiver->isModel() && $receiver->name !== null) {
                $related = $modelTableMap->relatedModelFor($receiver->name, $expr->name->toString());

                return $related === null ? ResolvedType::unknown() : ResolvedType::model($related);
            }
        }

        return ResolvedType::unknown();
    }

    private function resolveStaticCall(StaticCall $call, Node $context, ModelTableMap $modelTableMap): ResolvedType
    {
        $method = $this->methodName($call);

        if ($method === 'table' && $this->isDbFacade($call) && ($call->args[0]->value ?? null) instanceof String_) {
            return ResolvedType::table($call->args[0]->value->value);
        }

        if ($method === null || ! in_array($method, self::MODEL_ENTRYPOINTS, true) || ! $call->class instanceof Name) {
            return ResolvedType::unknown();
        }

        return $this->modelTypeFromName($call->class, $context, $modelTableMap);
    }

    private function modelTypeFromName(Name $name, Node $context, ModelTableMap $modelTableMap): ResolvedType
    {
        $fqcn = $this->resolveName($name);

        if ($modelTableMap->hasModel($fqcn)) {
            return ResolvedType::model($fqcn);
        }

        $matched = $this->matchKnownModel($fqcn, $modelTableMap);

        return $matched === null ? ResolvedType::unknown() : ResolvedType::model($matched);
    }

    private function resolveTypeNode(Node|string $type, Node $context, ModelTableMap $modelTableMap): ResolvedType
    {
        if ($type instanceof NullableType) {
            return $this->resolveTypeNode($type->type, $context, $modelTableMap);
        }

        if (! $type instanceof Name) {
            return ResolvedType::unknown();
        }

        return $this->modelTypeFromName($type, $context, $modelTableMap);
    }

    /**
     * @param array<string, ResolvedType> $symbols
     */
    private function applyVarDocblock(?Doc $doc, Node $context, ModelTableMap $modelTableMap, array &$symbols): void
    {
        if ($doc === null) {
            return;
        }

        if (! preg_match_all('/@var\s+([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)\s+\$([A-Za-z_][A-Za-z0-9_]*)/', $doc->getText(), $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $fqcn = ltrim($match[1], '\\');
            $matched = $this->matchKnownModel($fqcn, $modelTableMap);

            if ($matched !== null) {
                $symbols[$match[2]] = ResolvedType::model($matched);
            }
        }
    }

    private function matchKnownModel(string $fqcnOrShortName, ModelTableMap $modelTableMap): ?string
    {
        $needle = ltrim($fqcnOrShortName, '\\');

        foreach (array_keys($modelTableMap->all()) as $knownModel) {
            if ($knownModel === $needle || class_basename($knownModel) === $needle) {
                return $knownModel;
            }
        }

        return null;
    }

    private function scopeFor(Node $node): ClassMethod|Function_|\PhpParser\Node\Expr\Closure|null
    {
        if ($node instanceof ClassMethod || $node instanceof Function_ || $node instanceof \PhpParser\Node\Expr\Closure) {
            return $node;
        }

        $cursor = $node;

        while (($parent = $cursor->getAttribute('parent')) instanceof Node) {
            if ($parent instanceof ClassMethod || $parent instanceof Function_ || $parent instanceof \PhpParser\Node\Expr\Closure) {
                return $parent;
            }

            $cursor = $parent;
        }

        return null;
    }

    private function resolveName(Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        return ltrim(($resolved instanceof Name ? $resolved : $name)->toString(), '\\');
    }

    private function isDbFacade(Expr $expr): bool
    {
        if ($expr instanceof StaticCall) {
            return $expr->class instanceof Name && $this->isDbName($expr->class);
        }

        if (! $expr instanceof Name) {
            return false;
        }

        return $this->isDbName($expr);
    }

    private function isDbName(Name $name): bool
    {
        return in_array($this->resolveName($name), ['DB', 'Illuminate\Support\Facades\DB'], true);
    }

    private function methodName(MethodCall|StaticCall $call): ?string
    {
        return $call->name instanceof Identifier ? $call->name->toString() : null;
    }
}
