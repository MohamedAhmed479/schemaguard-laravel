# Testing And Commands

Read this before running validation or declaring a task complete. Skip it only for pure reading tasks with no final correctness claim.

## Environment / Bootstrap

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `composer install` | Fresh checkout or dependency drift. | Dependencies can install from `composer.lock`. | Completes without dependency conflicts. | Yes for dependency/package tasks; otherwise as needed. |
| `composer dump-autoload -o` | After class/file/autoload changes. | Optimized Composer autoload is valid. | Optimized autoload generated. | Yes for new PHP classes. |
| `composer validate --strict` | Package metadata changes or release readiness. | `composer.json` is valid. | `./composer.json is valid`. | Yes for package/dependency changes. |

## Validation

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `git status` | Start and finish of every task. | Worktree awareness. | Review current changes before proceeding. | Yes. |
| `git diff --check` | Start and finish of every task. | No whitespace/conflict-marker issues. | No output, exit 0. | Yes. |

## Phase 1 Commands

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `vendor/bin/phpunit tests/Feature/CheckCommandTest.php` | Command/provider/config changes. | Current command integration, including real Phase 5 pipeline behavior. | Test passes. | Yes for command changes. |
| `vendor/bin/testbench schemaguard:check` | Command integration changes. | Testbench can run the package command. | Prints a real Deployment Firewall result; with no migrations it is `RESULT: SAFE`. | Yes for command changes. |
| `vendor/bin/testbench vendor:publish --tag=schemaguard-config --force` | Config/provider publishing changes. | Package config publishes in Testbench. | `schemaguard.php` copied. | Yes for config/provider changes. |

## Phase 2 Commands

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `vendor/bin/phpunit --filter MigrationParserTest` | Parser or fixture changes. | Destructive event extraction, diagnostics, false-positive guards, and Phase 3 type changes. | Parser tests pass. | Yes for parser changes. |
| `vendor/bin/phpunit --filter MigrationDiscoveryTest` | Discovery/path behavior changes. | Explicit/pending discovery, sorting, non-PHP filtering, and Phase 5 Git diff behavior. | Discovery tests pass. | Yes for discovery changes. |

## Phase 3 Commands

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `vendor/bin/phpunit --filter CodebaseIndexerTest` | AST indexer changes. | Recursive discovery, ignore paths, safe parse failures, resolved names, parent links. | Indexer tests pass. | Yes for indexer changes. |
| `vendor/bin/phpunit --filter EloquentModelVisitorTest` | Model visitor changes. | Model schema, legacy accessor, relation, and computed accessor behavior. | Model visitor tests pass. | Yes for model visitor changes. |
| `vendor/bin/phpunit --filter EloquentUsageVisitorTest` | Eloquent query/property visitor changes. | Static model queries, typed property access, unresolved confidence. | Eloquent usage tests pass. | Yes for Eloquent usage changes. |
| `vendor/bin/phpunit --filter ApiResourceVisitorTest` | Resource visitor changes. | Resource `$this->column` exposure with proven model association and fallback confidence. | Resource tests pass. | Yes for resource visitor changes. |
| `vendor/bin/phpunit --filter ControllerVisitorTest` | Controller/FormRequest visitor changes. | Validation/rules keys are high confidence, request input/property access is medium, and Eloquent query logic is not duplicated. | Controller visitor tests pass. | Yes for controller visitor changes. |
| `vendor/bin/phpunit --filter LocalTypeResolverTest` | Type resolver changes. | Parameter, docblock, `new`, static model entrypoint, `DB::table`, and unknown resolution. | Type resolver tests pass. | Yes for type resolver changes. |
| `vendor/bin/phpunit --filter ColumnTokenMatcherTest` | Token matcher changes. | Rarity confidence and SQL-boundary matching. | Matcher tests pass. | Yes for matcher changes. |
| `vendor/bin/phpunit --filter StaticAnalysisScannerTest` | Scanner coordinator changes. | Two-pass scanner, target scoping, false-positive gate, dedupe, and failed parsed file skipping. | Scanner tests pass. | Yes for scanner changes. |

## Phase 4 Commands

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `vendor/bin/phpunit --filter RouteVisitorTest` | Route visitor changes. | Static AST route bindings for action/resource routes and dynamic-route rejection. | Route visitor tests pass. | Yes for route visitor changes. |
| `vendor/bin/phpunit --filter DependencyGraphTest` | Graph primitive changes. | Node/edge dedupe, cycle-safe traversal, exposed paths, missing node behavior. | Graph tests pass. | Yes for graph changes. |
| `vendor/bin/phpunit --filter DependencyGraphBuilderTest` | Graph builder changes. | Real fixture impact path from column to model to route. | Builder tests pass. | Yes for graph builder changes. |
| `vendor/bin/phpunit --filter PolicyConfigurationTest` | Policy config changes. | Typed config parsing and invalid config failures. | Config tests pass. | Yes for policy config changes. |
| `vendor/bin/phpunit --filter PolicyEngineTest` | Policy engine changes. | Full decision matrix, override precedence, exposure escalation, confidence floor, diagnostics. | Policy tests pass. | Yes for policy changes. |

## Phase 5 Commands

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `vendor/bin/phpunit --filter AnalysisRequestTest` | CLI option/request changes. | Request defaults, source selection, format validation, strict/no-cache, conflict handling. | Request tests pass. | Yes for request changes. |
| `vendor/bin/phpunit --filter AnalysisPipelineTest` | Pipeline changes. | No-event short-circuit, indexing/scanning orchestration, diagnostics, missing scan roots, progress callback. | Pipeline tests pass. | Yes for pipeline changes. |
| `vendor/bin/phpunit --filter MigrationDiscoveryTest` | Discovery changes. | Explicit/pending/Git diff discovery, sorting, filtering, failure handling. | Discovery tests pass. | Yes for discovery changes. |
| `vendor/bin/phpunit --filter ExitCodeResolverTest` | Exit-code changes. | SAFE/WARNING/BLOCK CI code mapping. | Exit-code tests pass. | Yes for exit-code changes. |
| `vendor/bin/phpunit --filter ConsoleReporterTest` | Reporter changes. | Console fragments, JSON schema, JSON fatal errors. | Reporter tests pass. | Yes for reporter changes. |
| `vendor/bin/phpunit tests/Feature/CheckCommandTest.php` | CLI integration changes. | End-to-end command verdicts, JSON-only output, strict behavior, missing scan root errors. | Feature tests pass. | Yes for CLI changes. |
| `vendor/bin/testbench schemaguard:check --migrations=tests/Fixtures/migrations/2024_06_01_000000_drop_phone_from_users.php --path=tests/Fixtures` | Manual CLI verification. | Real Testbench command returns BLOCK for used dropped column. | Output includes `RESULT: BLOCK`; exit code is 1. | Yes before Phase 5 completion. |
| `vendor/bin/testbench schemaguard:check --migrations=tests/Fixtures/migrations/2024_06_01_000000_drop_phone_from_users.php --path=tests/Fixtures --format=json` | Manual JSON verification. | JSON mode emits machine-readable result. | Valid JSON with `overall=BLOCK`; exit code is 1. | Yes before Phase 5 completion. |

## Phase 6 Commands

| Command | When to run | What it proves | Expected outcome | Mandatory |
| --- | --- | --- | --- | --- |
| `vendor/bin/phpunit --filter RawSqlVisitorTest` | Raw SQL scanning changes. | Static SQL matching, qualified/bare confidence, decoys, dynamic SQL diagnostics. | Test passes. | Yes for raw SQL changes. |
| `vendor/bin/phpunit --filter AstCacheTest` | Cache/indexer changes. | Cache miss/store/hit, invalidation, no-cache bypass, corruption miss, parent links after cache retrieval. | Test passes. | Yes for cache changes. |
| `vendor/bin/phpunit tests/Feature/CheckCommandTest.php` | End-to-end behavior changes. | Phase 6 feature matrix and golden JSON contract. | Test passes. | Yes for CLI/reporting changes. |
| `vendor/bin/phpunit --coverage-text` | Final Phase 6 verification. | Measured source coverage with a real coverage driver. | Overall `src/` >= 85%; parser/scanner/policy areas >= 90%. | Yes before Phase 6 completion. |

## Full Suite

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `vendor/bin/phpunit --testsuite Unit` | Any Phase 2/3/4/5 unit change. | All unit regressions remain green. | Unit suite passes. | Yes for Phase 2/3/4/5 source changes. |
| `vendor/bin/phpunit` | Any code/test change. | Full current package suite. | All tests pass. | Yes for code changes. |

## Troubleshooting

- Composer/autoload issues: run `composer dump-autoload -o`; verify PSR-4 namespace and file path match.
- Testbench command issues: verify `SchemaGuardServiceProvider` registers commands only when `runningInConsole()` and publishes with tag `schemaguard-config`.
- Malformed fixture failures: parser/indexer should return safe failed results and expose diagnostics where applicable; the full suite must not crash.
- Tests passing but context stale: source code wins; update `context/03_CURRENT_STATE.md`, `context/09_ACTIVE_WORK.md`, and any affected map/decision files.

## Release Readiness Commands

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Release |
| --- | --- | --- | --- | --- |
| `composer archive --format=zip --dir=build/release-audit` | Before a public release tag. | Composer export-ignore rules produce a clean package archive. | Archive contains runtime package files and excludes tests, fixtures, vendor, context, IDE/agent files, and build artifacts. | Yes. |
| Fresh Laravel path-repository install | Before first public release and after package metadata changes. | A clean Laravel app can install and auto-discover SchemaGuard without manual provider registration. | `php artisan schemaguard:check` is registered and runs. | Yes. |
| `php artisan vendor:publish --tag=schemaguard-config --force` in a fresh app | Before public release. | Config publishing works for consumers. | `config/schemaguard.php` exists and is valid PHP. | Yes. |
| Fresh-app explicit migration and JSON checks | Before public release. | Real consumer CLI behavior, exit codes, and JSON output are release-safe. | BLOCK/WARNING/SAFE behavior and JSON purity match README. | Yes. |
