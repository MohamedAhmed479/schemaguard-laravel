# Contributing to SchemaGuard

Thanks for helping improve SchemaGuard.

SchemaGuard is a Laravel package that statically analyzes migration changes and application code to produce a deployment decision: `SAFE`, `WARNING`, or `BLOCK`. The project is precision-first: high-confidence evidence may block, ambiguity should warn, and unsupported patterns should not pretend to be certain.

## Setup

```bash
composer install
composer dump-autoload -o
```

## Useful Test Commands

Run the broad checks before opening a pull request:

```bash
git diff --check
composer validate --strict
vendor/bin/phpunit
```

Run focused tests for the area you changed:

```bash
vendor/bin/phpunit --filter MigrationParserTest
vendor/bin/phpunit --filter StaticAnalysisScannerTest
vendor/bin/phpunit --filter PolicyEngineTest
vendor/bin/phpunit tests/Feature/CheckCommandTest.php
```

## Architecture Summary

SchemaGuard was built in phases:

- Foundation and Laravel package scaffolding.
- Migration extraction.
- AST discovery and usage scanning.
- Dependency graph and policy engine.
- CLI reporting, JSON output, and CI exit codes.
- Robustness hardening, raw SQL, cache, and golden JSON coverage.

The current Phase-1 product is released as `v0.1.0`.

## Product Rules

- Never execute host migrations, models, controllers, resources, routes, or database queries during analysis.
- Do not use regex to parse PHP source.
- Prefer conservative, testable confidence over broad matching.
- Dynamic or ambiguous code should produce honest diagnostics rather than fake certainty.
- Keep scoped pull requests focused; avoid unrelated refactors.

## Fixtures and Tests

When adding parser or scanner behavior:

- Add the smallest parsed-only fixture that proves the behavior.
- Add false-positive and false-negative regression coverage.
- Keep fixture migrations valid PHP unless the test intentionally covers malformed input.
- Do not execute fixture migrations or application classes.

## Context Updates

This repository has an agent context system under `context/`. After meaningful architecture, behavior, testing, release, or workflow changes, update the relevant context files so future maintainers do not have to rediscover the decision.

At minimum, consider:

- `context/03_CURRENT_STATE.md`
- `context/05_CODEBASE_MAP.md`
- `context/07_TESTING_AND_COMMANDS.md`
- `context/09_ACTIVE_WORK.md`
- `context/10_CHANGELOG.md`

## Bug Fixes vs New Capabilities

For bug fixes, include a minimal reproduction, a regression test, and the smallest safe correction.

For new capabilities, open or link a feature request first when the change affects parsing, scanner confidence, policy, CLI output, JSON shape, or cache behavior. Describe the expected `SAFE`, `WARNING`, or `BLOCK` result and how false positives will be controlled.

## Versioning and Releases

Release tags are created intentionally by the maintainer. Do not move existing tags, create release tags, publish GitHub releases, or publish to Packagist from a pull request.

This repository does not currently claim an automated release workflow.
