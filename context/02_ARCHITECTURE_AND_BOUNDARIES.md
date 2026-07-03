# Architecture And Boundaries

Read this for phase boundaries, parser guarantees, migration extraction, usage scanning, graph, or policy work. Skip it for README-only edits or package hygiene work that does not touch architecture.

## Phased Architecture

- P1 Foundation: Implemented and verified.
- P2 Migration Extraction: Implemented and verified.
- P3 AST Discovery: Implemented and verified.
- P4 Graph + Policy: Implemented and verified.
- P5 CLI + Pipeline: Planned - not implemented.
- P6 Robustness: Planned - not implemented.

Exact current boundary:

```text
Phase 1, Phase 2, Phase 3, and Phase 4 are acceptance-verified.
Phase 5 has not started.
```

## Current Extraction Flow

Status: Implemented and verified.

```text
Migration files
  -> MigrationDiscovery
  -> MigrationParser
  -> SchemaChangeEvent[]
```

`MigrationParser` uses PHP-Parser:

```php
(new \PhpParser\ParserFactory())->createForNewestSupportedVersion()
```

The public parser contract remains stable:

```php
MigrationParser::parseMany(array $paths): array
MigrationParser::parseFile(string $path): array
```

Both return `SchemaChangeEvent[]`.

## Current Scanner Flow

Status: Implemented and verified.

```text
Source PHP files
  -> CodebaseIndexer
  -> StaticAnalysisScanner
  -> Usage[]
```

The scanner is target-scoped:

```text
SchemaChangeEvent[]
  -> SymbolTargetSet
  -> visitor matching only relevant table/column targets
```

## Current Logic Flow

Status: Implemented and verified.

```text
SchemaChangeEvent[] + Usage[] + RouteBinding[]
  -> DependencyGraphBuilder
  -> DependencyGraph
  -> PolicyEngine
  -> PolicyResult
```

Phase 4 route discovery is static AST route-file scanning only. It produces `RouteBinding[]` for graph building and does not execute Laravel routes.

## Current Parser Guarantees

Status: Implemented and verified.

Evidence: `MigrationParserTest`.

- Detects `$table->dropColumn('column')`.
- Detects `$table->dropColumn(['a', 'b'])`.
- Detects `$table->renameColumn('old', 'new')`.
- Detects `Schema::drop('table')`.
- Detects `Schema::dropIfExists('table')`.
- Detects `$table->string('email')->change()` as `COLUMN_TYPE_CHANGED`.
- Supports custom Blueprint closure variable names.
- Emits indeterminate events for dynamic destructive arguments.
- Ignores destructive operations in `down()` methods.
- Does not parse comments or string literals as code.
- Degrades safely on missing or malformed migration files.
- Exposes diagnostics through `MigrationParser::diagnostics()`.

## Current Scanner Guarantees

Status: Implemented and verified.

Evidence: Phase 3 scanning tests.

- Indexes PHP files recursively and decorates AST nodes once with `NameResolver` and `ParentConnectingVisitor`.
- Failed PHP parses become `ParsedFile::failed(...)` and do not abort scans.
- Builds `ModelTableMap` before usage visitors run.
- Detects model schema, Eloquent query/property, API resource, controller validation, controller request input, and relation usages.
- Uses `LocalTypeResolver` for conservative intra-procedural type inference.
- Uses rarity confidence for unresolved property/query receivers.
- Rejects coincidental strings, arbitrary array keys, and local variables through the false-positive fixture gate.

## Current Logic Guarantees

Status: Implemented and verified.

Evidence: Phase 4 graph, route, and policy tests.

- `DependencyGraph` stores typed nodes and deterministic adjacency-list edges.
- Duplicate nodes and edges are idempotent.
- Unknown edge endpoints throw instead of corrupting the graph.
- `reachableFrom()` and `exposedPaths()` are cycle-safe.
- Exposed paths end at `Route` or `Resource` nodes.
- `RouteVisitor` detects controller actions and resource routes from AST route files.
- `DependencyGraphBuilder` builds impact paths from scanned usage and route evidence.
- `PolicyEngine` implements the full 12-cell decision matrix.
- Override precedence is ignore, enforce, per-type mode, then custom rule.
- `PolicyConfiguration` validates enum-like config and throws `ConfigurationException` on invalid modes.

## Current Phase 4 Non-Goals

Status: Planned - not implemented.

- Raw SQL visitor.
- CLI pipeline.
- Console reporter.
- Exit-code resolver.
- JSON output.
- Git diff migration discovery implementation.
- AST cache.

## Do Not Accidentally Pull These Features Forward

Do not sneak these into Phase 4 maintenance tasks:

- Full analysis pipeline.
- Enhanced CLI options.
- Console reporter.
- Exit-code resolver.
- JSON output.
- Git diff migration discovery implementation.
- Raw SQL visitor.
- Caching and Phase 6 robustness work.
