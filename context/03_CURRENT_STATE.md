# Current State

Read this at the start of every task. Skip it only if you just updated it in the same task after running the required checks.

## Status Table

| Area | Status | Evidence | Notes |
| --- | --- | --- | --- |
| Composer/package foundation | Implemented and verified | `composer.json`; `composer validate --strict` | Package name, MIT license, PHP 8.2, Illuminate 11/12, PHP-Parser dependency, Testbench, PHPUnit, PSR-4, Laravel provider auto-discovery. |
| Laravel provider and config publishing | Implemented and verified | `src/SchemaGuardServiceProvider.php`; `vendor/bin/testbench vendor:publish --tag=schemaguard-config --force` | Uses `mergeConfigFrom`, publishes `schemaguard-config`, registers command only in console. |
| Phase 1 command | Implemented and verified | `src/Console/Commands/CheckCommand.php`; `tests/Feature/CheckCommandTest.php`; `vendor/bin/testbench schemaguard:check` | Prints Deployment Firewall banner and "No analysis wired yet."; performs no analysis. |
| Value objects | Implemented and verified | `src/ValueObjects/*`; PHPUnit suite | References, source locations, schema change events, confidence, surfaces, usages, rarity, and target sets are present. |
| Migration discovery | Implemented and verified | `src/Migrations/MigrationDiscovery.php`; `tests/Unit/Migrations/MigrationDiscoveryTest.php` | Explicit paths and pending paths only; Git diff throws not-supported until Phase 5. |
| AST migration parser | Implemented and verified | `src/Migrations/MigrationParser.php`; `src/Migrations/Visitors/SchemaCallVisitor.php`; `tests/Unit/Migrations/MigrationParserTest.php` | Uses PHP-Parser, preserves `parseMany()`/`parseFile()`, detects `COLUMN_TYPE_CHANGED`, and keeps Phase 2 regressions green. |
| Diagnostics | Implemented and verified | `MigrationParser::diagnostics()`; parser diagnostics tests | Missing/malformed files return `[]` and expose diagnostics without leaking across `parseFile()` runs. |
| Phase 2 fixtures/tests | Implemented and verified | `tests/Fixtures/migrations/*`; targeted parser/discovery tests | Covers drops, renames, table drops, arrays, dynamic args, `down()`, comments/strings, malformed source. |
| Phase 3 AST foundation | Implemented and verified | `src/Scanning/ParsedFile.php`; `src/Scanning/CodebaseIndexer.php`; `tests/Unit/Scanning/CodebaseIndexerTest.php` | Recursively indexes PHP files, applies `NameResolver` and `ParentConnectingVisitor`, and degrades broken PHP to failed parsed files. |
| Phase 3 usage scanner | Implemented and verified | `src/Scanning/StaticAnalysisScanner.php`; scanning visitors; `tests/Unit/Scanning/*VisitorTest.php` | Two-pass scanner builds `ModelTableMap`, detects model/query/resource/controller usages, and passes target-scoping, false-positive, and dedupe gates. |
| Phase 4 graph/policy | Planned — not implemented | `IMPLEMENTATION_ROADMAP.md` Phase 4 | No graph or decision matrix engine exists. |
| Phase 5 CLI/pipeline | Planned — not implemented | `IMPLEMENTATION_ROADMAP.md` Phase 5 | Current CLI has no analysis options, JSON output, or pipeline. |
| Phase 6 robustness | Planned — not implemented | `IMPLEMENTATION_ROADMAP.md` Phase 6 | No Phase 6 cache/robustness implementation exists. |

## Acceptance Evidence

Last verified against current working tree: 2026-07-03.

Current command evidence:

- `git status`: PASS; current working tree contains uncommitted Phase 3 source, tests, fixtures, and context changes; no commit was created.
- `git diff --check`: PASS; no whitespace errors. PowerShell/Git may print LF-to-CRLF warnings for touched files.
- `composer validate --strict`: PASS; `./composer.json is valid`.
- `composer dump-autoload -o`: PASS; optimized autoload generated.
- `composer install`: PASS; lock file install verified, nothing to install/update/remove.
- `vendor/bin/phpunit --filter CodebaseIndexerTest`: PASS; 5 tests, 18 assertions.
- `vendor/bin/phpunit --filter MigrationParserTest`: PASS; 19 tests, 83 assertions.
- `vendor/bin/phpunit --filter EloquentModelVisitorTest`: PASS; 6 tests, 20 assertions.
- `vendor/bin/phpunit --filter EloquentUsageVisitorTest`: PASS; 3 tests, 11 assertions.
- `vendor/bin/phpunit --filter ApiResourceVisitorTest`: PASS; 2 tests, 8 assertions.
- `vendor/bin/phpunit --filter ControllerVisitorTest`: PASS; 4 tests, 12 assertions.
- `vendor/bin/phpunit --filter LocalTypeResolverTest`: PASS; 1 test, 13 assertions.
- `vendor/bin/phpunit --filter ColumnTokenMatcherTest`: PASS; 2 tests, 8 assertions.
- `vendor/bin/phpunit --filter StaticAnalysisScannerTest`: PASS; 6 tests, 26 assertions.
- `vendor/bin/phpunit --filter MigrationDiscoveryTest`: PASS; 6 tests, 6 assertions.
- `vendor/bin/phpunit --testsuite Unit`: PASS; 56 tests, 210 assertions.
- `vendor/bin/phpunit`: PASS; 57 tests, 213 assertions.
- `vendor/bin/testbench schemaguard:check`: PASS; prints the Deployment Firewall scaffold output.

Caveat: this Phase 3 acceptance audit intentionally leaves uncommitted source, tests, fixtures, and context changes in the working tree. Future agents must inspect current Git state rather than relying on this snapshot.
