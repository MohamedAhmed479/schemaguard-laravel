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
| Release readiness | Implemented and verified | Fresh Laravel 12 path-repository install; Composer archive audit; `RELEASE_CHECKLIST.md` | Local release readiness passed before publication. |
| Public release | Published and verified | GitHub release `SchemaGuard v0.1.0`; tag `v0.1.0`; Packagist `schemaguard/laravel` version `v0.1.0`; public install verification | Published from commit `ee9fbdfdc3beffd358b594e58f99967d331fd100`; Packagist auto-updates through GitHub. |
| Future hosted product areas | Planned - not implemented | Roadmap out-of-scope list | No GitHub App, SaaS dashboard, hosted PR service, multi-repo orchestration, ML calibration, or non-Laravel parser. |

## Acceptance Evidence

Last verified against current working tree: 2026-07-04.

Post-release status:

- Phase-1 product implementation: complete and verified.
- Local release readiness: verified.
- GitHub repository: `MohamedAhmed479/schemaguard-laravel`.
- GitHub release: `SchemaGuard v0.1.0`, published.
- Release tag: `v0.1.0`.
- Release commit: `ee9fbdfdc3beffd358b594e58f99967d331fd100`.
- Packagist package: `schemaguard/laravel`, published.
- Packagist version: `v0.1.0`.
- Packagist updates: auto-updated through GitHub.
- Public install verification: passed in a clean Laravel app using `composer require schemaguard/laravel:^0.1 -W`; auto-discovery, config publishing, SAFE/BLOCK/WARNING behavior, JSON output, and strict warning handling were verified.
- No installs, stars, downloads, users, or adoption counts have been verified.

Current command evidence:

- `git status`: PASS before this post-release documentation update; `master` was up to date with `origin/master` and clean.
- `git diff --check`: PASS; no whitespace errors. Git may print LF-to-CRLF warnings on Windows.
- `git log --oneline --decorate -8`: PASS; `HEAD -> master`, `origin/master`, and tag `v0.1.0` point to `ee9fbdf chore: prepare v0.1.0 release`.
- `git tag --list "v0.1.0"`: PASS; `v0.1.0` exists.
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
- Public Packagist install audit: PASS; a clean Laravel app installed `schemaguard/laravel v0.1.0` from the public Packagist/GitHub distribution, provider auto-discovered, `schemaguard:check` registered, config published, empty app returned `SAFE` exit 0, used dropped column returned `BLOCK` exit 1, JSON output decoded cleanly with `overall=BLOCK` and `exit_code=1`, used type change returned `WARNING` exit 0, and `--strict` returned `WARNING` exit 1.
- Composer archive audit: PASS after export-ignore hardening; archive contains runtime package files (`src/`, `config/`, `README.md`, `LICENSE.md`, `composer.json`) and excludes tests, fixtures, vendor, context, IDE/agent files, Testbench config, and build artifacts.
