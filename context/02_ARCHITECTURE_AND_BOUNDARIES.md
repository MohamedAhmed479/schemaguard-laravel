# Architecture And Boundaries

Read this for phase boundaries, parser guarantees, migration extraction, usage scanning, graph, policy, CLI, or robustness work. Skip it for README-only edits or package hygiene work that does not touch architecture.

## Phased Architecture

- P1 Foundation: Implemented and verified.
- P2 Migration Extraction: Implemented and verified.
- P3 AST Discovery: Implemented and verified.
- P4 Graph + Policy: Implemented and verified.
- P5 CLI + Pipeline: Implemented and verified.
- P6 Robustness: Implemented and verified.

Exact current boundary:

```text
Phase 1 through Phase 6 are implemented and verified.
The Phase-1 product implementation is complete.
```

## Current Pipeline

Status: Implemented and verified.

```text
schemaguard:check
  -> AnalysisRequest
  -> AnalysisPipeline
  -> MigrationDiscovery
  -> MigrationParser
  -> CodebaseIndexer
  -> StaticAnalysisScanner
  -> RouteVisitor
  -> DependencyGraphBuilder
  -> PolicyEngine
  -> ConsoleReporter
  -> ExitCodeResolver
```

The command supports explicit migrations, pending migrations, local Git diff migration discovery, path overrides, console output, JSON output, strict warning handling, and `--no-cache`. `--no-cache` bypasses the optional AST cache.

## Parser Guarantees

Status: Implemented and verified.

- Uses PHP-Parser internally.
- Preserves `MigrationParser::parseMany(array $paths): array` and `MigrationParser::parseFile(string $path): array`.
- Returns `SchemaChangeEvent[]`.
- Detects dropped columns, renamed columns, dropped tables, `dropIfExists`, array drops, dynamic destructive arguments, and `->change()` type changes.
- Ignores destructive operations in `down()` methods.
- Supports custom Blueprint closure variable names.
- Keeps table context isolated across closures.
- Marks same-table/same-column drop/re-add in the same `up()` migration as neutralized and emits diagnostics.
- Degrades safely on missing or malformed migration files and exposes diagnostics.

## Scanner Guarantees

Status: Implemented and verified.

- Recursively indexes PHP source files with `NameResolver` and `ParentConnectingVisitor`.
- Failed PHP parses become `ParsedFile::failed(...)` and continue through analysis metadata/diagnostics.
- Builds `ModelTableMap` before usage visitors run.
- Detects model schema, Eloquent query/property, API resource, controller validation/input, relation, and raw SQL usages.
- Uses conservative intra-procedural type inference.
- Handles complex Eloquent relationship keys and relation traversal where statically proven.
- Treats computed modern accessors and `$appends`-only attributes as virtual unless backed by real model-schema evidence.
- Rejects coincidental strings, arbitrary array keys, local variables, and SQL substring decoys.
- Raw SQL matching is token-boundary based, not full SQL grammar parsing.
- Dynamic raw SQL emits diagnostics rather than guessed usages.

## Graph And Policy Guarantees

Status: Implemented and verified.

- `DependencyGraph` stores typed deterministic nodes and edges.
- Duplicate nodes and edges are idempotent.
- Unknown edge endpoints throw instead of corrupting the graph.
- Reachability and exposed-path traversal are cycle-safe.
- Static `RouteVisitor` produces `RouteBinding[]` from route ASTs without executing routes.
- `PolicyEngine` implements the decision matrix and override order.
- Ignore, enforce, per-type mode, custom rules, exposure escalation, and confidence floor are tested.

## Reporting Guarantees

Status: Implemented and verified.

- Console output includes counts, findings, usage tables, blast-radius paths, diagnostics, and a final `RESULT: ...`.
- JSON mode emits one JSON document only.
- JSON includes schema version, overall result, counts, exit code, analyzed metadata, findings, and diagnostics.
- Paths in JSON are normalized to repository-relative paths where possible.
- Fatal configuration/scan-root errors produce concise console output or valid JSON error output.

## Phase 6 Robustness Guarantees

Status: Implemented and verified.

- `RawSqlVisitor` supports static first-string raw SQL calls and builder raw methods.
- Qualified raw SQL matches are HIGH confidence; bare matches are MEDIUM; raw SQL is never DEFINITIVE.
- `AstCache` is optional, content/mtime/path keyed, corruption-tolerant, and bypassable with `--no-cache`.
- Golden JSON E2E output is source-controlled.
- README and coverage gates are part of Phase 6 verification.

## Do Not Accidentally Pull These Features Forward

Do not sneak these into Phase-1 product maintenance tasks:

- Hosted PR checks or GitHub App integration.
- SaaS/dashboard work.
- Multi-repository orchestration.
- Non-Laravel framework parsing.
- ML calibration.
