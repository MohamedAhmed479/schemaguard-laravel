# Architecture And Boundaries

Read this for phase boundaries, parser guarantees, migration extraction, or usage scanning work. Skip it for README-only edits or package hygiene work that does not touch architecture.

## Phased Architecture

- P1 Foundation: Implemented and verified.
- P2 Migration Extraction: Implemented and verified.
- P3 AST Discovery: Implemented and verified.
- P4 Graph + Policy: Planned — not implemented.
- P5 CLI + Pipeline: Planned — not implemented.
- P6 Robustness: Planned — not implemented.

Exact current boundary:

```text
Phase 1, Phase 2, and Phase 3 are acceptance-verified.
Phase 4 has not started.
```

## Current Extraction Flow

Status: Implemented and verified.

```text
Migration files
  -> MigrationDiscovery
  -> MigrationParser
  -> SchemaChangeEvent[]
```

`MigrationParser` now uses PHP-Parser for Phase 3 scope:

```php
(new \PhpParser\ParserFactory())->createForNewestSupportedVersion()
```

The Phase 2 token parser was replaced during Phase 3B. The public parser contract remains stable:

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

## Current Phase 3 Non-Goals

Status: Planned — not implemented.

- Route scanning.
- Raw SQL visitor.
- Dependency graph.
- Policy engine.
- CLI pipeline.
- JSON output.
- Git diff migration discovery.
- AST cache.

## Do Not Accidentally Pull These Features Forward

Do not sneak these into Phase 3 maintenance tasks:

- Dependency graph.
- Policy engine.
- Full analysis pipeline.
- Enhanced CLI options.
- JSON output.
- Git diff migration discovery.
- Route scanning.
- Raw SQL visitor.
- Caching and Phase 6 robustness work.
