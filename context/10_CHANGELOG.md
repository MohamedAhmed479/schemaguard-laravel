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
