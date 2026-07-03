# Codebase Map

Read this when locating files for a task. Skip it if the exact file is already known and the task is trivial.

## Package Foundation

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `composer.json` | Composer package identity, dependencies, autoload, Laravel provider discovery, scripts. | Package setup, dependency, autoload, or provider questions. | Keep package name `schemaguard/laravel`; keep PHP-Parser dependency. |
| `testbench.yaml` | Testbench CLI provider registration. | Testbench command issues. | Keeps `vendor/bin/testbench schemaguard:check` deterministic outside PHPUnit. |
| `README.md` | Minimal installation, command, and config publish usage. | User-facing package docs change. | Do not claim full analysis CLI is wired until Phase 5. |
| `LICENSE.md` | MIT license. | License/package hygiene tasks. | Do not modify casually. |
| `.gitattributes` | Composer export-ignore rules. | Package distribution hygiene. | Keep tests, `phpunit.xml.dist`, and `.gitattributes` excluded. |

## Configuration

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `config/schemaguard.php` | Publishable package config surface. | Config publishing, scanner config, or Testbench issues. | Some keys are future-facing; do not describe policy/CLI behavior as active until consumed by code. |

## Console

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Console/Commands/CheckCommand.php` | Phase 1 smoke-test command. | Command registration/output changes. | Must remain `schemaguard:check`; no Phase 5 options until pipeline work begins. |

## Migrations

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Migrations/MigrationDiscovery.php` | Resolves migration files. | Migration file selection or path behavior. | Supports explicit and pending path strategies only; Git diff is Phase 5 and throws not-supported. |
| `src/Migrations/MigrationParser.php` | AST-backed migration parser. | Migration parsing task. | Preserves `parseMany` and `parseFile`; diagnostics must remain public. |
| `src/Migrations/Visitors/SchemaCallVisitor.php` | AST visitor for migration schema calls. | `up()` scope, Blueprint variable, table context, or type-change detection changes. | Must ignore `down()`, avoid table-context leaks, and emit indeterminate events for dynamic destructive arguments. |

## Scanning

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Scanning/ParsedFile.php` | Immutable parsed/failed source file value. | AST indexing or parse failure handling. | Broken PHP becomes failed parsed file, not an aborting exception. |
| `src/Scanning/CodebaseIndexer.php` | Recursively discovers and parses PHP files once. | AST indexing or scan path changes. | Applies `NameResolver` and `ParentConnectingVisitor`; no AST cache yet. |
| `src/Scanning/LocalTypeResolver.php` | Conservative intra-procedural type inference. | Property/query receiver resolution. | No cross-method/container/interface flow. Unknowns remain explicit. |
| `src/Scanning/ColumnTokenMatcher.php` | Rarity and SQL-boundary token helper. | Unresolved confidence or future raw SQL work. | `matchesInSql()` exists, but no Raw SQL visitor exists yet. |
| `src/Scanning/ModelTableMap.php` | Maps model FQCNs to tables and simple relations. | Model registration or table-binding changes. | Uses Eloquent naming convention when `$table` is absent. |
| `src/Scanning/StaticAnalysisScanner.php` | Phase 3 two-pass usage scanner coordinator. | Scanner integration work. | Returns `Usage[]`; does not build graph, policy findings, or CLI output. |
| `src/Scanning/Visitors/AbstractUsageVisitor.php` | Base for target-scoped usage visitors. | Adding or changing usage visitors. | `reset()` must prevent leakage between files/scans. |
| `src/Scanning/Visitors/EloquentModelVisitor.php` | Model registration and model-schema usage detection. | Model table, fillable/casts/accessor/relation/scope changes. | Computed modern accessors must not become fake backing-column usages. |
| `src/Scanning/Visitors/EloquentUsageVisitor.php` | Eloquent query and property access usage detection. | Query builder or property access changes. | Bare array keys and unrelated strings must not become usages. |
| `src/Scanning/Visitors/ApiResourceVisitor.php` | API resource exposure detection. | Resource scanning changes. | Only resource classes and `toArray()` should emit resource usage. |
| `src/Scanning/Visitors/ControllerVisitor.php` | Controller/FormRequest validation and request input detection. | Controller scanning changes. | Request input is medium confidence; validation keys are high confidence. |

## Value Objects

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/ValueObjects/ChangeType.php` | Destructive event enum. | Event model changes. | `COLUMN_TYPE_CHANGED` is detected by AST migration parser. |
| `src/ValueObjects/TableReference.php` | Immutable table identity. | Reference equality/id tasks. | Stable `id()` format matters for graph/policy phases. |
| `src/ValueObjects/ColumnReference.php` | Immutable table+column identity. | Column event or equality tasks. | `equals()` is semantic; do not weaken. |
| `src/ValueObjects/SourceLocation.php` | File/line/optional column source metadata. | Diagnostics or event location changes. | `fromNode()` is used by AST visitors. |
| `src/ValueObjects/SchemaChangeEvent.php` | Event payload and named constructors. | Parser event output changes. | Keep `renamedTo`, `newType`, `indeterminate`, and reason behavior stable. |
| `src/ValueObjects/Confidence.php` | Ordered usage confidence enum. | Usage scanner or future policy work. | `atLeast()` ordering is used by scanner dedupe and later policy. |
| `src/ValueObjects/SurfaceType.php` | Usage surface enum. | Usage scanner or graph/policy work. | `RAW_SQL` exists as a future surface; no Raw SQL visitor exists yet. |
| `src/ValueObjects/Usage.php` | Usage evidence payload. | Scanner visitor changes. | Holds symbol, surface, confidence, location, and detail. |
| `src/ValueObjects/SymbolTargetSet.php` | Scanner target scope derived from schema events. | Scanner target matching changes. | Scanner must use this target set, not generic string search. |

## Tests

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `tests/TestCase.php` | Testbench bootstrap. | Provider/config test issues. | Must register `SchemaGuardServiceProvider`. |
| `tests/Feature/CheckCommandTest.php` | Phase 1 command smoke test. | Command behavior changes. | Proves command registration, banner, and exit code. |
| `tests/Unit/Migrations/MigrationParserTest.php` | Migration parser regression suite. | Parser changes. | Covers Phase 2 destructive operations plus Phase 3 type changes. |
| `tests/Unit/Migrations/MigrationDiscoveryTest.php` | Migration discovery suite. | Discovery changes. | Must preserve sorting, `.php` filtering, explicit validation, and Git diff rejection. |
| `tests/Unit/Scanning/CodebaseIndexerTest.php` | Phase 3A AST indexing suite. | Indexer changes. | Proves recursive discovery, ignore paths, parse failures, resolved names, parent links. |
| `tests/Unit/Scanning/*VisitorTest.php` | Phase 3C usage visitor suites. | Usage scanner changes. | Includes model/query/resource/controller coverage, target scoping, false-positive gates, and dedupe coverage. |
| `tests/Unit/Scanning/ControllerVisitorTest.php` | Direct controller/FormRequest visitor suite. | Controller validation, request input, or FormRequest rules behavior changes. | Validation/rules keys are high confidence; request input/property access stays medium. |
| `tests/Unit/ValueObjects/ReferenceTest.php` | Value object identity/equality tests. | Reference object changes. | Keep ids stable. |

## Fixtures

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `tests/Fixtures/migrations/` | Parsed-but-never-executed migration fixtures. | Parser/discovery test changes. | Fixtures should remain valid PHP except intentional malformed fixture elsewhere. |
| `tests/Fixtures/Models/` | Parsed-only model fixtures. | Model map or model visitor changes. | Do not execute these files during analysis. |
| `tests/Fixtures/Http/` | Parsed-only resource/controller fixtures. | Resource/controller scanner changes. | Do not execute these files during analysis. |
| `tests/Fixtures/false_positive.php` | False-positive scanner gate. | Usage visitor changes. | Must yield zero usages for `users.phone`. |
| `tests/Fixtures/broken_syntax.php` | Indexer resilience fixture. | Indexer parse failure handling. | Must become `ParsedFile::failed(...)`. |
| `tests/Fixtures/malformed/broken_migration.fixture` | Migration parser resilience fixture. | Parser diagnostics changes. | Must never crash the test suite. |

## Specifications

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `IMPLEMENTATION_ROADMAP.md` | Canonical phase plan and Definition of Done. | Any phase work. | Build top-to-bottom; do not skip phase gates. |
| `TECHNICAL_BLUEPRINT.md` | Detailed architecture specification. | Design or future phase implementation. | Contains future graph/policy/CLI details that may not exist yet. |

## Read Only When Needed

- Do not load every fixture unless changing parser/scanner behavior.
- Do not inspect `vendor/` unless debugging dependency behavior.
- Do not add graph/policy/CLI behavior while working on Phase 3 maintenance.
