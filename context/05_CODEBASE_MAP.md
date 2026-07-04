# Codebase Map

Read this when locating files for a task. Skip it if the exact file is already known and the task is trivial.

## Package Foundation

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `composer.json` | Composer package identity, dependencies, autoload, Laravel provider discovery, scripts. | Package setup, dependency, autoload, or provider questions. | Keep package name `schemaguard/laravel`; keep PHP-Parser dependency. |
| `testbench.yaml` | Testbench CLI provider registration. | Testbench command issues. | Keeps `vendor/bin/testbench schemaguard:check` deterministic outside PHPUnit. |
| `README.md` | User-facing installation, CLI, JSON, exit-code, CI, and limitation docs. | User-facing package docs change. | Must stay honest about conservative inference and raw SQL limitations. |
| `CONTRIBUTING.md` | Contributor setup, workflow, test, fixture, and release-boundary guidance. | Contributor workflow or PR expectations change. | Do not claim automated release workflows that do not exist. |
| `SECURITY.md` | Security reporting guidance. | Security contact or vulnerability reporting process changes. | Contains a maintainer-replacement placeholder; do not invent a private email. |
| `LICENSE.md` | MIT license. | License/package hygiene tasks. | Do not modify casually. |
| `.gitattributes` | Composer export-ignore rules. | Package distribution hygiene. | Keep tests, fixtures, vendor, context/agent docs, planning docs, Testbench config, build artifacts, `phpunit.xml.dist`, and `.gitattributes` excluded from Composer archives. |
| `RELEASE_CHECKLIST.md` | Reusable public-release process checklist. | Release preparation or publication tasks. | Process aid only; do not treat it as proof that a release was published. |

## Community Workflow

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `.github/ISSUE_TEMPLATE/bug_report.yml` | Structured bug report form. | Issue intake fields or support workflow changes. | Must ask for minimal migration/code samples and warn against pasting secrets. |
| `.github/ISSUE_TEMPLATE/feature_request.yml` | Structured feature request form. | Feature intake changes. | Keep precision-first and conservative-analysis expectations visible. |
| `.github/ISSUE_TEMPLATE/config.yml` | GitHub issue template configuration. | Issue-template routing changes. | Blank issues stay disabled; do not invent Discussions links. |
| `.github/pull_request_template.md` | Contributor PR checklist. | PR expectations change. | Requires tests, context/docs notes, and false-positive/false-negative considerations. |

## Configuration

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `config/schemaguard.php` | Publishable package config surface. | Config publishing, scanner config, or Testbench issues. | Defaults must be safe; enforce/ignore/custom examples should remain commented unless intentionally active. |

## Console

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Console/Commands/CheckCommand.php` | CLI entry point. | Command registration/output changes. | Parses options, runs `AnalysisPipeline`, renders via `ConsoleReporter`, resolves exit code; `--no-cache` bypasses AST cache. |

## Pipeline

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Pipeline/AnalysisRequest.php` | Immutable normalized CLI request. | CLI option or request validation changes. | Rejects conflicting migration source options and invalid formats. |
| `src/Pipeline/MigrationSource.php` | Migration source enum. | Discovery strategy changes. | Valid sources are pending, explicit, and Git diff. |
| `src/Pipeline/OutputFormat.php` | Output format enum. | Reporter or CLI format changes. | Valid formats are console and JSON only. |
| `src/Pipeline/AnalysisPipeline.php` | Phase 5 orchestration flow. | Pipeline integration changes. | No rendering; no CLI parsing; no host code execution. |
| `src/Pipeline/AnalysisMetadata.php` | Run metadata for reporting. | JSON `analyzed` changes. | Keep metadata separate from policy decisions. |
| `src/Pipeline/AnalysisRunResult.php` | Policy result plus run metadata. | Reporter integration changes. | Do not pollute `PolicyResult` with CLI-only metadata. |

## Migrations

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Migrations/MigrationDiscovery.php` | Resolves migration files. | Migration file selection or path behavior. | Supports explicit, pending, and local Git diff strategies. |
| `src/Migrations/GitCommandRunner.php` | Testable Git command runner seam. | Git diff discovery changes. | Commands are arrays, not shell-concatenated strings. |
| `src/Migrations/NativeGitCommandRunner.php` | Native Git process execution. | Git process behavior changes. | Used only for local Git diff migration discovery. |
| `src/Migrations/MigrationParser.php` | AST-backed migration parser. | Migration parsing task. | Preserves `parseMany` and `parseFile`; diagnostics must remain public. |
| `src/Migrations/Visitors/SchemaCallVisitor.php` | AST visitor for migration schema calls. | `up()` scope, Blueprint variable, table context, or type-change detection changes. | Must ignore `down()`, avoid table-context leaks, and emit indeterminate events for dynamic destructive arguments. |

## Scanning

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Scanning/ParsedFile.php` | Immutable parsed/failed source file value. | AST indexing or parse failure handling. | Broken PHP becomes failed parsed file, not an aborting exception. |
| `src/Scanning/CodebaseIndexer.php` | Recursively discovers and parses PHP files. | AST indexing, cache, or scan path changes. | Applies `NameResolver` and `ParentConnectingVisitor`; uses optional `AstCache` when enabled. |
| `src/Scanning/AstCache.php` | Optional parsed-AST cache. | Cache behavior or `--no-cache` changes. | Cache failures degrade to miss; never writes cache under source/test/fixture directories. |
| `src/Scanning/LocalTypeResolver.php` | Conservative intra-procedural type inference. | Property/query receiver resolution. | No cross-method/container/interface flow. Unknowns remain explicit. |
| `src/Scanning/ColumnTokenMatcher.php` | Rarity and SQL-boundary token helper. | Unresolved confidence or raw SQL work. | Raw SQL matching is conservative token-boundary matching, not SQL grammar parsing. |
| `src/Scanning/Visitors/RawSqlVisitor.php` | Static raw SQL usage visitor. | Raw SQL detection changes. | Static SQL only; qualified matches are HIGH, bare matches MEDIUM, dynamic SQL becomes diagnostics. |
| `src/Scanning/ModelTableMap.php` | Maps model FQCNs to tables and simple relations. | Model registration or table-binding changes. | Uses Eloquent naming convention when `$table` is absent. |
| `src/Scanning/StaticAnalysisScanner.php` | Phase 3 two-pass usage scanner coordinator. | Scanner integration work. | Returns `Usage[]`; does not build graph, policy findings, or CLI output. |
| `src/Scanning/Visitors/AbstractUsageVisitor.php` | Base for target-scoped usage visitors. | Adding or changing usage visitors. | `reset()` must prevent leakage between files/scans. |
| `src/Scanning/Visitors/EloquentModelVisitor.php` | Model registration and model-schema usage detection. | Model table, fillable/casts/accessor/relation/scope changes. | Computed modern accessors must not become fake backing-column usages. |
| `src/Scanning/Visitors/EloquentUsageVisitor.php` | Eloquent query and property access usage detection. | Query builder or property access changes. | Bare array keys and unrelated strings must not become usages. |
| `src/Scanning/Visitors/ApiResourceVisitor.php` | API resource exposure detection. | Resource scanning changes. | Only resource classes and `toArray()` should emit resource usage. |
| `src/Scanning/Visitors/ControllerVisitor.php` | Controller/FormRequest validation and request input detection. | Controller scanning changes. | Request input is medium confidence; validation keys are high confidence. |
| `src/Scanning/Visitors/RouteVisitor.php` | Static route-file visitor producing `RouteBinding[]`. | Route-to-controller graph work. | Emits no `Usage`; ignores unsupported dynamic routes safely. |

## Graph

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Graph/GraphNode.php` | Typed graph node value. | Node identity or label changes. | IDs are stable; labels are for display/path rendering only. |
| `src/Graph/NodeType.php` | Graph node type enum. | Adding graph surfaces. | Keep graph identity stable for policy/reporting. |
| `src/Graph/ImpactPath.php` | Ordered path from changed symbol to exposed surface. | Blast-radius path rendering. | `__toString()` renders labels, not opaque IDs. |
| `src/Graph/DependencyGraph.php` | Deterministic adjacency-list graph. | Reachability or path changes. | Unknown edge endpoints throw; exposed sinks are routes/resources. |
| `src/Graph/DependencyGraphBuilder.php` | Builds graph from parsed index, usages, and route bindings. | Phase 4 graph integration changes. | No policy decisions and no route inference from filenames. |

## Policy

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Policy/PolicyConfiguration.php` | Typed config wrapper and override application. | Policy config or override changes. | Override order is ignore, enforce, mode, custom rule. |
| `src/Policy/PolicyMode.php` | Per-change mode enum. | Policy mode changes. | Valid modes are `block`, `warn`, and `off`. |
| `src/Policy/CustomRule.php` | Typed custom severity override. | Custom rule matching changes. | Matching custom rules have highest precedence. |
| `src/Policy/EventFinding.php` | One event plus relevant usages, severity, and paths. | Policy result shape changes. | No rendering or CLI behavior. |
| `src/Policy/PolicyResult.php` | Aggregated policy result and counts. | Result aggregation changes. | Counts are derived from findings. |
| `src/Policy/PolicyEngine.php` | Deterministic matrix and override evaluator. | Verdict logic changes. | `COLUMN_TYPE_CHANGED` with high usage is WARNING by default. |

## Output

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Output/ConsoleReporter.php` | Console and JSON rendering. | Report formatting or JSON schema changes. | JSON mode must output exactly one JSON document and no progress. |
| `src/Output/ExitCodeResolver.php` | Maps `PolicyResult` to CI exit code. | Exit-code changes. | Does not decide policy severity. |

## Value Objects

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/ValueObjects/ChangeType.php` | Destructive event enum. | Event model changes. | `COLUMN_TYPE_CHANGED` is detected by AST migration parser. |
| `src/ValueObjects/TableReference.php` | Immutable table identity. | Reference equality/id tasks. | Stable `id()` format matters for graph/policy phases. |
| `src/ValueObjects/ColumnReference.php` | Immutable table+column identity. | Column event or equality tasks. | `equals()` is semantic; do not weaken. |
| `src/ValueObjects/SourceLocation.php` | File/line/optional column source metadata. | Diagnostics or event location changes. | `fromNode()` is used by AST visitors. |
| `src/ValueObjects/SchemaChangeEvent.php` | Event payload and named constructors. | Parser event output changes. | Keep `renamedTo`, `newType`, `indeterminate`, and reason behavior stable. |
| `src/ValueObjects/Confidence.php` | Ordered usage confidence enum. | Usage scanner or future policy work. | `atLeast()` ordering is used by scanner dedupe and later policy. |
| `src/ValueObjects/SurfaceType.php` | Usage surface enum. | Usage scanner or graph/policy work. | Includes `RAW_SQL`; raw SQL confidence never becomes DEFINITIVE. |
| `src/ValueObjects/Usage.php` | Usage evidence payload. | Scanner visitor changes. | Holds symbol, surface, confidence, location, and detail. |
| `src/ValueObjects/SymbolTargetSet.php` | Scanner target scope derived from schema events. | Scanner target matching changes. | Scanner must use this target set, not generic string search. |
| `src/ValueObjects/Severity.php` | Ordered SAFE/WARNING/BLOCK enum. | Policy or result aggregation changes. | Int ordering drives max severity aggregation. |
| `src/ValueObjects/RouteBinding.php` | Static route-to-controller binding. | Route visitor or graph builder changes. | Produced from AST only; no runtime router. |

## Tests

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `tests/TestCase.php` | Testbench bootstrap. | Provider/config test issues. | Must register `SchemaGuardServiceProvider`. |
| `tests/Feature/CheckCommandTest.php` | Command integration and golden JSON suite. | Command behavior changes. | Proves BLOCK/SAFE/WARNING, strict warnings, JSON purity, raw SQL, ignored/enforced symbols, broken source diagnostics, neutralization, and golden JSON. |
| `tests/Unit/Migrations/MigrationParserTest.php` | Migration parser regression suite. | Parser changes. | Covers Phase 2 destructive operations plus Phase 3 type changes. |
| `tests/Unit/Migrations/MigrationDiscoveryTest.php` | Migration discovery suite. | Discovery changes. | Must preserve sorting, `.php` filtering, explicit validation, and Git diff command/filter/failure behavior. |
| `tests/Unit/Scanning/CodebaseIndexerTest.php` | Phase 3A AST indexing suite. | Indexer changes. | Proves recursive discovery, ignore paths, parse failures, resolved names, parent links. |
| `tests/Unit/Scanning/*VisitorTest.php` | Phase 3C usage visitor suites. | Usage scanner changes. | Includes model/query/resource/controller coverage, target scoping, false-positive gates, and dedupe coverage. |
| `tests/Unit/Scanning/ControllerVisitorTest.php` | Direct controller/FormRequest visitor suite. | Controller validation, request input, or FormRequest rules behavior changes. | Validation/rules keys are high confidence; request input/property access stays medium. |
| `tests/Unit/Scanning/RouteVisitorTest.php` | Route visitor suite. | Route scanning changes. | Covers action routes, mutation route, resource expansion, and dynamic-route rejection. |
| `tests/Unit/Graph/DependencyGraphTest.php` | Graph primitive suite. | Graph reachability/path changes. | Covers dedupe, cycles, missing nodes, and exposed paths. |
| `tests/Unit/Graph/DependencyGraphBuilderTest.php` | Real fixture graph builder suite. | Graph builder changes. | Proves `users.phone -> App\Models\User -> UserController@show -> GET /api/users/{user}`. |
| `tests/Unit/Policy/PolicyConfigurationTest.php` | Policy config validation suite. | Config schema or validation changes. | Invalid modes throw `ConfigurationException`. |
| `tests/Unit/Policy/PolicyEngineTest.php` | Matrix and override suite. | Verdict logic changes. | Covers all 12 matrix cells and override precedence. |
| `tests/Unit/Pipeline/AnalysisRequestTest.php` | CLI request validation suite. | Request option changes. | Covers defaults, explicit/diff source selection, strict, no-cache, invalid format, conflicts. |
| `tests/Unit/Pipeline/AnalysisPipelineTest.php` | Pipeline orchestration suite. | Pipeline changes. | Covers no-event short-circuit, scan-root failure, diagnostics, progress callback. |
| `tests/Unit/Output/ExitCodeResolverTest.php` | CI exit-code suite. | Exit-code changes. | Covers SAFE/BLOCK/WARNING strict/config combinations. |
| `tests/Unit/Output/ConsoleReporterTest.php` | Console/JSON reporter suite. | Reporter changes. | Covers console fragments, JSON schema, and JSON fatal output. |
| `tests/Feature/CheckCommandTest.php` | Command E2E suite. | CLI behavior changes. | Covers Phase 5 and Phase 6 end-to-end scenarios. |
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
| `tests/Fixtures/routes/api.php` | Parsed-only route fixture. | CLI or route-to-controller graph changes. | Used for blast-radius paths. |
| `fixtures/phase4_app/` | Parsed-only mini Laravel-like app for Phase 4 graph tests. | Graph builder or route visitor changes. | Kept outside test autoload; export-ignored from Composer archives. |

## Specifications

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `IMPLEMENTATION_ROADMAP.md` | Canonical phase plan and Definition of Done. | Any phase work. | Build top-to-bottom; do not skip phase gates. |
| `TECHNICAL_BLUEPRINT.md` | Detailed architecture specification. | Design or future phase implementation. | Contains future graph/policy/CLI details that may not exist yet. |

## Read Only When Needed

- Do not load every fixture unless changing parser/scanner behavior.
- Do not inspect `vendor/` unless debugging dependency behavior.
- Do not add hosted integration work, SaaS, multi-repository orchestration, non-Laravel parsing, or ML calibration during Phase-1 product maintenance.
