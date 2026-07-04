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

## Phase 4 - Logic Engine

Status: Implemented and verified.

- Added ordered `Severity` enum.
- Added typed graph nodes, impact paths, deterministic dependency graph, and graph builder.
- Added static AST route visitor producing `RouteBinding[]` for graph construction.
- Added typed policy configuration, custom rules, policy findings/results, and policy engine.
- Implemented the full SAFE/WARNING/BLOCK decision matrix.
- Implemented override precedence: ignore, enforce, per-type mode, then custom rule.
- Added Phase 4 mini-app fixtures outside test autoload and export-ignored them from package archives.
- Preserved the Phase 1 `schemaguard:check` scaffold; no Phase 5 pipeline was wired.

Verification evidence:

- `vendor/bin/phpunit --filter RouteVisitorTest`
- `vendor/bin/phpunit --filter DependencyGraphTest`
- `vendor/bin/phpunit --filter DependencyGraphBuilderTest`
- `vendor/bin/phpunit --filter PolicyConfigurationTest`
- `vendor/bin/phpunit --filter PolicyEngineTest`
- `vendor/bin/phpunit --testsuite Unit`

## Phase 4 - Acceptance Audit Coverage Pass

Status: Implemented and verified.

- Strengthened route visitor tests to prove full API resource mapping and web resource expansion.
- Strengthened graph tests for stable IDs, source locations, resource sinks, duplicate path suppression, and label rendering.
- Strengthened graph-builder tests for core relationships and no public exposure inflation from model-only usage.
- Strengthened policy tests for table-drop usage matching, rename original-column matching, empty results, confidence-floor behavior, exposure boundaries, config singleton resolution, and override conflict precedence.

Verification evidence:

- `vendor/bin/phpunit --filter RouteVisitorTest`
- `vendor/bin/phpunit --filter DependencyGraphTest`
- `vendor/bin/phpunit --filter DependencyGraphBuilderTest`
- `vendor/bin/phpunit --filter PolicyConfigurationTest`
- `vendor/bin/phpunit --filter PolicyEngineTest`
- `vendor/bin/phpunit --testsuite Unit`

## Phase 5 - Presentation & CLI

Status: Implemented and verified.

- Added immutable `AnalysisRequest`, migration source and output format enums.
- Added `AnalysisPipeline`, `AnalysisRunResult`, and `AnalysisMetadata`.
- Completed local Git diff migration discovery through a testable command-runner seam.
- Added `CodebaseScanException` for missing scan roots.
- Added CI `ExitCodeResolver`.
- Replaced the scaffold command with the real `schemaguard:check` pipeline and options.
- Added console reporting, JSON output, JSON fatal errors, and console-mode indexing progress.
- Added feature tests for BLOCK, SAFE, WARNING, strict warnings, JSON-only output, and missing scan root failures.
- At Phase 5 completion time, preserved then-future Phase 6 boundaries: no raw SQL visitor, no AST cache, no hosted integrations.

Verification evidence:

- `vendor/bin/phpunit --filter AnalysisRequestTest`
- `vendor/bin/phpunit --filter AnalysisPipelineTest`
- `vendor/bin/phpunit --filter MigrationDiscoveryTest`
- `vendor/bin/phpunit --filter ExitCodeResolverTest`
- `vendor/bin/phpunit --filter ConsoleReporterTest`
- `vendor/bin/phpunit tests/Feature/CheckCommandTest.php`
- `vendor/bin/phpunit --testsuite Unit`
- `vendor/bin/phpunit`
- `vendor/bin/testbench schemaguard:check`

## Phase 5 - Acceptance Audit Corrections

Status: Implemented and verified.

- Strengthened CLI feature tests for console fatal errors and JSON SAFE/WARNING/fatal purity.
- Strengthened pipeline tests to prove no-event runs do not emit indexing progress.
- Strengthened Git diff discovery tests to prove deterministic sorting and filtering of returned paths.
- Added container-resolution coverage for Phase 5 collaborators.
- Changed default `enforce` config examples from active rules to commented examples so an unused destructive change is SAFE by default.

Verification evidence:

- `vendor/bin/phpunit --filter AnalysisPipelineTest`
- `vendor/bin/phpunit --filter MigrationDiscoveryTest`
- `vendor/bin/phpunit --filter PolicyConfigurationTest`
- `vendor/bin/phpunit tests/Feature/CheckCommandTest.php`
- `vendor/bin/phpunit --testsuite Unit`
- `vendor/bin/phpunit`

## Phase 6 - Edge Cases & Robustness

Status: Implemented and verified.

- Added conservative `RawSqlVisitor` coverage for static DB/raw builder SQL strings with HIGH qualified matches, MEDIUM bare matches, substring decoy protection, and dynamic SQL diagnostics.
- Hardened migration handling for multi-table closure isolation and same-migration drop/re-add neutralization with visible diagnostics.
- Strengthened scanner behavior for complex Eloquent relationships, relation traversal, modern accessor and `$appends` virtual attribute correctness, and conservative model recognition.
- Added optional `AstCache` with content/mtime/path keys, cache-miss degradation, `--no-cache` bypass, and parent-link preservation after cache retrieval.
- Added golden JSON E2E coverage and expanded CLI feature scenarios for used drops, unused drops, renames, type changes, raw-SQL-only usage, enforced/ignored symbols, broken source files, and neutralized drops.
- During final acceptance audit, strengthened regression proof for non-neutralized unrelated re-adds and JSON-visible raw SQL/neutralization diagnostics.
- Completed README usage, JSON, exit-code, CI, and limitation documentation.
- Added PHPUnit source coverage filter and verified `src/` line coverage at 91.85% with Xdebug.

Verification evidence:

- `vendor/bin/phpunit --filter RawSqlVisitorTest`
- `vendor/bin/phpunit --filter AstCacheTest`
- `vendor/bin/phpunit --filter MigrationParserTest`
- `vendor/bin/phpunit --filter EloquentModelVisitorTest`
- `vendor/bin/phpunit --filter LocalTypeResolverTest`
- `vendor/bin/phpunit --filter StaticAnalysisScannerTest`
- `vendor/bin/phpunit tests/Feature/CheckCommandTest.php`
- `vendor/bin/phpunit --testsuite Unit`
- `vendor/bin/phpunit`
- `vendor/bin/phpunit --coverage-text`

## Release Readiness - Local Validation

Status: Implemented and verified.

- Added package discovery keywords to `composer.json`.
- Hardened Composer export rules to exclude dev dependencies, tests, fixtures, agent/context docs, planning docs, IDE files, Testbench config, and build artifacts from archives.
- Commented default ignore examples so fresh installs do not silently ignore host symbols.
- Expanded README requirements/options/limitations for first-user clarity.
- Added `RELEASE_CHECKLIST.md` for reusable release operations.
- Verified a fresh Laravel 12 app can install the package via a local Composer path repository, auto-discover the provider, register `schemaguard:check`, publish config, return SAFE/BLOCK/WARNING verdicts, emit pure JSON, and run without a live database connection.

Verification evidence:

- `composer validate --strict`
- `composer archive --format=zip --dir=build/release-audit`
- Fresh Laravel path-repository install and command checks
- `vendor/bin/phpunit`
- `vendor/bin/phpunit --coverage-text`

## Public Release - v0.1.0

Status: Published and verified.

- Tagged and pushed `v0.1.0` for release commit `ee9fbdfdc3beffd358b594e58f99967d331fd100`.
- Published GitHub Release `SchemaGuard v0.1.0` for `MohamedAhmed479/schemaguard-laravel`.
- Packagist detected `schemaguard/laravel` version `v0.1.0` and is configured for GitHub auto-updates.
- Verified public Composer installation from Packagist in a clean Laravel app with no path repository.
- Verified provider auto-discovery, `schemaguard:check` command registration, config publishing, empty-app `SAFE/0`, used-drop `BLOCK/1`, type-change `WARNING/0`, strict warning `WARNING/1`, and JSON output purity.

Verification evidence:

- `git tag --list "v0.1.0"`
- `composer require schemaguard/laravel:^0.1 -W` in a clean Laravel app
- `composer show schemaguard/laravel`
- `php artisan package:discover --ansi`
- `php artisan list --raw`
- `php artisan schemaguard:check`
- `php artisan vendor:publish --tag=schemaguard-config --force`

## Post-Release Community Workflow

Status: Implemented.

- Added GitHub Issue Forms for bug reports and feature requests.
- Added issue-template configuration with blank issues disabled and support routed through GitHub Issues.
- Added a pull request template requiring problem statement, behavior impact, test commands, context/docs updates, breaking-change declaration, performance/cache impact, JSON contract impact, and false-positive/false-negative considerations.
- Added concise contributor guidance for setup, tests, architecture, fixtures, context updates, bug fixes vs new capabilities, and release-tag boundaries.
- Added security reporting guidance without inventing a private email or claiming GitHub private vulnerability reporting is enabled.

Verification evidence:

- `git diff --check`
- Local YAML structure validation for `.github/ISSUE_TEMPLATE/*.yml`

## Post-Release Demo Assets

Status: Implemented.

- Added `docs/demo/README.md` documenting the verified demo scenario and README embed snippet.
- Added `docs/demo/blocking-a-used-column-drop.svg`, a terminal-style visual based on a real local SchemaGuard run against a temporary Laravel app.
- Verified the demo scenario drops `users.email`, detects live Laravel model usage, reports `RESULT: BLOCK`, and returns exit code 1.
- Kept local absolute paths out of the demo SVG.

Verification evidence:

- Real `php artisan schemaguard:check --migrations=... --path=app --no-ansi` run in a temporary Laravel app.
- SVG XML validation.
- `git diff --check`
