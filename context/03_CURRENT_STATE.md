# Current State

Read this at the start of every task. Skip it only if you just updated it in the same task after running the required checks.

## Status Table

| Area | Status | Evidence | Notes |
| --- | --- | --- | --- |
| Composer/package foundation | Implemented and verified | `composer.json`; `composer validate --strict` | Package name, MIT license, PHP 8.2, Illuminate 11/12, PHP-Parser, Testbench, PHPUnit, PSR-4, provider auto-discovery. |
| Laravel provider and config | Implemented and verified | `src/SchemaGuardServiceProvider.php`; config publish tests | Merges config, publishes `schemaguard-config`, registers command, and binds Phase 4/5/6 collaborators. |
| CLI command | Implemented and verified | `src/Console/Commands/CheckCommand.php`; `tests/Feature/CheckCommandTest.php` | Runs the full pipeline, renders console/JSON, handles fatal errors, and returns CI exit codes. |
| Migration discovery | Implemented and verified | `src/Migrations/MigrationDiscovery.php`; `MigrationDiscoveryTest` | Supports explicit, pending, and local Git diff migration discovery. |
| Migration parser | Implemented and verified | `src/Migrations/MigrationParser.php`; `src/Migrations/Visitors/SchemaCallVisitor.php`; `MigrationParserTest` | PHP-Parser based; supports drops, renames, table drops, type changes, dynamic events, `down()` exclusion, table isolation, and neutralization. |
| Value objects | Implemented and verified | `src/ValueObjects/*`; PHPUnit suite | References, schema events, source locations, confidence, rarity, usage surfaces, severity, routes, and target sets. |
| AST foundation | Implemented and verified | `src/Scanning/ParsedFile.php`; `src/Scanning/CodebaseIndexer.php`; `CodebaseIndexerTest` | Recursive PHP indexing, broken-file degradation, `NameResolver`, and `ParentConnectingVisitor`. |
| Usage scanner | Implemented and verified | `src/Scanning/StaticAnalysisScanner.php`; scanning visitor tests | Model/query/resource/controller/relation/raw SQL usages, target scoping, false-positive gates, dedupe, and diagnostics. |
| Raw SQL scanning | Implemented and verified | `src/Scanning/Visitors/RawSqlVisitor.php`; `RawSqlVisitorTest` | Static SQL only; qualified matches HIGH, bare matches MEDIUM, dynamic SQL diagnostics, substring decoy protection. |
| AST cache | Implemented and verified | `src/Scanning/AstCache.php`; `AstCacheTest` | Optional, path/mtime/content-keyed, corruption-tolerant, `--no-cache` bypass, parent links restored after cache retrieval. |
| Graph/policy | Implemented and verified | `src/Graph/*`; `src/Policy/*`; graph/policy tests | Dependency paths, route exposure, decision matrix, overrides, confidence floors, exposure escalation, counts. |
| Reporting/JSON | Implemented and verified | `src/Output/*`; `CheckCommandTest`; golden JSON fixture | Console report, JSON-only output, fatal JSON, normalized paths, golden E2E contract. |
| README/package docs | Implemented and verified | `README.md` | Installation, usage, JSON, exit codes, CI example, and honest limitations. |
| Release readiness | Verified locally | Fresh Laravel 12 path-repository install; Composer archive audit; `RELEASE_CHECKLIST.md` | Package not published; no release tag such as `v0.1.0` was created. |
| Future hosted product areas | Planned - not implemented | Roadmap out-of-scope list | No GitHub App, SaaS dashboard, hosted PR service, multi-repo orchestration, ML calibration, or non-Laravel parser. |

## Acceptance Evidence

Last verified against current working tree: 2026-07-04.

Current command evidence:

- `git status`: PASS; current working tree contains uncommitted Phase 6 implementation, tests, fixtures, README/config, PHPUnit coverage-filter, final acceptance-audit test hardening, and context updates; no commit was created.
- `git diff --check`: PASS; no whitespace errors. Git may print LF-to-CRLF warnings on Windows.
- `composer validate --strict`: PASS; `./composer.json is valid`.
- `composer dump-autoload -o`: PASS; optimized autoload generated without PSR-4 warnings after fixture namespace cleanup.
- `composer install`: PASS; lock file install verified, nothing to install/update/remove.
- `vendor/bin/phpunit --filter RawSqlVisitorTest`: PASS; 5 tests, 12 assertions.
- `vendor/bin/phpunit --filter AstCacheTest`: PASS; 7 tests, 12 assertions.
- `vendor/bin/phpunit --filter MigrationParserTest`: PASS; 23 tests, 103 assertions.
- `vendor/bin/phpunit --filter MigrationDiscoveryTest`: PASS; 7 tests, 9 assertions.
- `vendor/bin/phpunit --filter EloquentModelVisitorTest`: PASS; 10 tests, 39 assertions.
- `vendor/bin/phpunit --filter LocalTypeResolverTest`: PASS; 2 tests, 22 assertions.
- `vendor/bin/phpunit --filter StaticAnalysisScannerTest`: PASS; 8 tests, 31 assertions.
- `vendor/bin/phpunit tests/Feature/CheckCommandTest.php`: PASS; 17 tests, 125 assertions.
- `vendor/bin/phpunit --testsuite Unit`: PASS; 152 tests, 502 assertions.
- `vendor/bin/phpunit`: PASS; 169 tests, 627 assertions.
- `php -m`: PASS; Xdebug is loaded.
- `php --ri xdebug`: PASS; Xdebug 3.4.4 has coverage enabled.
- `php --ri pcov`: Informational FAIL; PCOV is not installed, but Xdebug coverage is available and used.
- `vendor/bin/phpunit --coverage-text`: PASS; overall `src/` line coverage is 91.85% (1859/2024). Parser, scanner aggregate, and policy areas meet the Phase 6 target.
- `vendor/bin/testbench schemaguard:check`: PASS; real pipeline output with `RESULT: SAFE`.
- `vendor/bin/testbench schemaguard:check --migrations=tests/Fixtures/migrations/2024_06_01_000000_drop_phone_from_users.php --path=tests/Fixtures`: PASS; expected `RESULT: BLOCK`, exit code 1.
- `vendor/bin/testbench schemaguard:check --migrations=tests/Fixtures/migrations/2024_06_01_000000_drop_phone_from_users.php --path=tests/Fixtures --format=json`: PASS; JSON output validated with `json_decode`, expected exit code 1.
- Fresh Laravel install audit: PASS; Laravel 12 app installed `schemaguard/laravel` via local Composer path repository, provider auto-discovered, `schemaguard:check` registered, config published, explicit drop returned `BLOCK` exit 1, type change returned `WARNING` exit 0 and `--strict` exit 1, JSON output stayed machine-safe.
- Composer archive audit: PASS after export-ignore hardening; archive contains runtime package files (`src/`, `config/`, `README.md`, `LICENSE.md`, `composer.json`) and excludes tests, fixtures, vendor, context, IDE/agent files, Testbench config, and build artifacts.

Caveat: release readiness was verified locally only. Publishing to Packagist, pushing tags, and creating a GitHub Release have not been performed.
