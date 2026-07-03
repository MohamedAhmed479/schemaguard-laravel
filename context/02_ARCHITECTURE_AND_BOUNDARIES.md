# Architecture And Boundaries

Read this for phase boundaries, parser guarantees, or migration extraction work. Skip it for README-only edits or package hygiene work that does not touch architecture.

## Phased Architecture

- P1 Foundation: Implemented and verified.
- P2 Migration Extraction: Implemented and verified.
- P3 AST Discovery: Planned — not implemented.
- P4 Graph + Policy: Planned — not implemented.
- P5 CLI + Pipeline: Planned — not implemented.
- P6 Robustness: Planned — not implemented.

Exact current boundary:

```text
Phase 1 and Phase 2 are acceptance-verified.
Phase 3 has not started.
```

## Current Extraction Flow

Status: Implemented and verified.

```text
Migration files
  -> MigrationDiscovery
  -> MigrationParser
  -> SchemaChangeEvent[]
```

`MigrationParser` intentionally uses the tokenizer for Phase 2 scope:

```php
token_get_all($source, TOKEN_PARSE)
```

The AST migration parser is Phase 3 work.

## Stable Parser Contract

Status: Implemented and verified.

```php
MigrationParser::parseMany(array $paths): array
MigrationParser::parseFile(string $path): array
```

Both return `SchemaChangeEvent[]`. Preserve this public contract when upgrading internals.

## Current Parser Guarantees

Status: Implemented and verified.

Evidence: `MigrationParserTest`.

- Detects `$table->dropColumn('column')`.
- Detects `$table->dropColumn(['a', 'b'])`.
- Detects `$table->renameColumn('old', 'new')`.
- Detects `Schema::drop('table')`.
- Detects `Schema::dropIfExists('table')`.
- Emits indeterminate events for dynamic destructive arguments.
- Ignores destructive operations in `down()` methods.
- Does not parse comments or string literals as code.
- Degrades safely on missing or malformed migration files.
- Exposes diagnostics through `MigrationParser::diagnostics()`.

## Known Phase 2 Non-Goals

Status: Planned — not implemented.

- AST-backed migration parsing.
- `SchemaCallVisitor`.
- `->change()` fluent-chain detection.
- Custom Blueprint variable handling beyond current token parser scope.
- Full cross-method or cross-file analysis.
- Codebase usage discovery.

`COLUMN_TYPE_CHANGED` exists in `ChangeType`, but detection through `->change()` is not implemented yet.

## Do Not Accidentally Pull These Features Forward

Do not sneak these into Phase 1/2 maintenance tasks:

- AST codebase scanner.
- `CodebaseIndexer`.
- `ParsedFile`.
- PHP-Parser visitor system.
- AST migration parser.
- `SchemaCallVisitor`.
- `COLUMN_TYPE_CHANGED` detection.
- Confidence model.
- Model/resource/controller usage scanning.
- Dependency graph.
- Policy engine.
- Full analysis pipeline.
- Enhanced CLI options.
- JSON output.
- Git diff migration discovery.
- Raw SQL scanning.
- Caching and Phase 6 robustness work.
