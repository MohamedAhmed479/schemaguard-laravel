# Phase Plan

Read this for phase navigation or when deciding the next safe implementation task. Skip it for narrow bug fixes after phase scope is already clear.

This is not a duplicate of [../IMPLEMENTATION_ROADMAP.md](../IMPLEMENTATION_ROADMAP.md). The roadmap remains canonical.

| Phase | Objective | Status | Canonical Reference | Entry Gate | Exit Gate | Primary Artifacts |
| --- | --- | --- | --- | --- | --- | --- |
| Phase 1 | Establish Laravel package foundation and smoke-test command. | Implemented and verified | Roadmap Phase 1 | Empty or package-start repo. | Composer, provider, config publishing, command, Testbench smoke test green. | `composer.json`, `config/schemaguard.php`, service provider, `CheckCommand`, test harness. |
| Phase 2 | Extract destructive migration events into `SchemaChangeEvent[]`. | Implemented and verified | Roadmap Phase 2 | Phase 1 DoD green. | Parser/discovery/value-object tests and full suite green. | `MigrationDiscovery`, `MigrationParser`, value objects, migration fixtures. |
| Phase 3 | Build AST discovery and upgrade migration parsing where required. | Implemented and verified | Roadmap Phase 3 | Phase 2 DoD green against current tree. | AST scanner tests, Phase 2 parser regression green, type-change detection proven. | `ParsedFile`, `CodebaseIndexer`, AST `MigrationParser`, `SchemaCallVisitor`, usage scanner. |
| Phase 4 | Convert changes and usages into graph/policy decisions. | Implemented and verified | Roadmap Phase 4 | Phase 3 usage model green. | Decision matrix, override, route, and graph tests green. | Graph, route bindings, policy engine, decision objects. |
| Phase 5 | Wire full CLI and analysis pipeline. | Implemented and verified | Roadmap Phase 5 | Phase 4 decision engine green. | End-to-end CLI tests green. | CLI options, pipeline orchestration, output rendering, JSON, exit codes. |
| Phase 6 | Add robustness, performance, and hardening. | Planned - not implemented | Roadmap Phase 6 | Full Phase 5 pipeline green. | Robustness/performance gates green. | Cache, raw SQL scanning, edge handling, expanded fixtures. |

## Phase 3 Completed Milestones

Status: Implemented and verified.

Implemented scope:

- `ParsedFile`
- `CodebaseIndexer`
- PHP-Parser integration
- `NameResolver`
- `ParentConnectingVisitor`
- Graceful broken-PHP handling
- AST-backed `MigrationParser`
- Stable `parseMany(array $paths): array` and `parseFile(string $path): array`
- `SchemaCallVisitor`
- Correct `up()` scope
- Custom Blueprint variable handling
- `COLUMN_TYPE_CHANGED` through `->change()`
- `Confidence`, `Usage`, `ModelTableMap`, `LocalTypeResolver`, and `ColumnTokenMatcher`
- Model, Eloquent usage, API resource, and controller visitors
- False-positive fixture gate

## Phase 4 Completed Milestones

Status: Implemented and verified.

Implemented scope:

- `Severity`
- `GraphNode`, `NodeType`, `ImpactPath`, and `DependencyGraph`
- Static AST `RouteVisitor` producing `RouteBinding[]`
- `DependencyGraphBuilder`
- `PolicyConfiguration`, `PolicyMode`, and custom rule validation
- `EventFinding`, `PolicyResult`, and `PolicyEngine`
- Full decision matrix, override precedence, exposure escalation, and confidence-floor tests

## Phase 5 Completed Milestones

Status: Implemented and verified.

Implemented scope:

- `AnalysisRequest`, `MigrationSource`, and output format validation
- `AnalysisPipeline`, `AnalysisRunResult`, and `AnalysisMetadata`
- Local Git diff migration discovery
- `CodebaseScanException` for missing scan roots
- `ExitCodeResolver`
- `ConsoleReporter` console and JSON output
- Real `schemaguard:check` CLI wiring
- Console-mode progress during indexing
- Feature-level CLI tests for BLOCK, SAFE, WARNING, strict, JSON, and scan-root errors

Do not begin Phase 6 until Phase 5 remains green against the current working tree.
