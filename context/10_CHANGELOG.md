# Engineering Changelog

Read this to understand milestone-level changes. Skip it when you only need the current state table.

This is not a Git log replacement. Append entries after tests are run.

## Phase 1 - Foundation & Package Scaffolding

Status: Implemented and verified.

- Added Composer package definition for `schemaguard/laravel`.
- Added Laravel package auto-discovery provider.
- Added publishable `config/schemaguard.php`.
- Added `schemaguard:check` scaffold command.
- Added Testbench harness and command smoke test.
- Added README, MIT license, PHPUnit config, and package hygiene files.

Verification evidence:

- `composer validate --strict`
- `vendor/bin/phpunit tests/Feature/CheckCommandTest.php`
- `vendor/bin/testbench schemaguard:check`
- `vendor/bin/testbench vendor:publish --tag=schemaguard-config --force`

## Phase 2 - Extraction Engine

Status: Implemented and verified.

- Added destructive change enum and immutable value objects.
- Added `MigrationDiscovery` for explicit and pending migration path resolution.
- Added token-based `MigrationParser`.
- Detects column drops, array-based column drops, column renames, `Schema::drop`, and `Schema::dropIfExists`.
- Emits indeterminate events for dynamic destructive arguments.
- Excludes destructive calls in `down()` methods.
- Ignores comments and string literals as code.
- Degrades safely on missing/malformed files and exposes diagnostics.
- Added fixtures and focused parser/discovery tests.

Verification evidence:

- `vendor/bin/phpunit --filter MigrationParserTest`
- `vendor/bin/phpunit --filter MigrationDiscoveryTest`
- `vendor/bin/phpunit`

## Context System - Agent Operating Memory

Status: Implemented and verified.

Final validation passed for this documentation task.

- Added root agent pointer.
- Added layered `context/` operating memory.
- Added current state, phase plan, codebase map, engineering rules, testing catalog, decision log, active work handoff, changelog, and update protocol.
- Added copy-ready task handoff and decision record templates.

Verification evidence:

- `git diff --check`
- `vendor/bin/phpunit`
- Re-read `context/00_START_HERE.md` as a new-agent bootstrap.

## Phase 3 - Discovery Engine

Status: Implemented and verified.

- Added AST indexing foundation with `ParsedFile` and `CodebaseIndexer`.
- Upgraded `MigrationParser` internals to PHP-Parser and added `SchemaCallVisitor`.
- Added `COLUMN_TYPE_CHANGED` detection through fluent `->change()` chains.
- Added confidence, surface, usage, rarity, and target-set value objects.
- Added conservative local type resolver, column token matcher, model table map, usage visitors, and two-pass static scanner.
- Added parsed-only fixtures for models, resources, controllers, false positives, unresolved receivers, malformed files, and AST migration parser behavior.
- Added focused Phase 3 tests and preserved Phase 2 parser/discovery regressions.
- Strengthened final acceptance coverage for direct controller/FormRequest behavior, local type resolver entrypoints, API resource fallback confidence, model table mapping, scanner target scoping, false positives, and dedupe.

Verification evidence:

- `vendor/bin/phpunit --filter CodebaseIndexerTest`
- `vendor/bin/phpunit --filter MigrationParserTest`
- `vendor/bin/phpunit --filter EloquentModelVisitorTest`
- `vendor/bin/phpunit --filter EloquentUsageVisitorTest`
- `vendor/bin/phpunit --filter ApiResourceVisitorTest`
- `vendor/bin/phpunit --filter ControllerVisitorTest`
- `vendor/bin/phpunit --filter LocalTypeResolverTest`
- `vendor/bin/phpunit --filter ColumnTokenMatcherTest`
- `vendor/bin/phpunit --filter StaticAnalysisScannerTest`
- `vendor/bin/phpunit --testsuite Unit`
- `vendor/bin/phpunit`
