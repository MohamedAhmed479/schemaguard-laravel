# Phase Plan

Read this for phase navigation or when deciding the next safe implementation task. Skip it for narrow bug fixes after phase scope is already clear.

This is not a duplicate of [../IMPLEMENTATION_ROADMAP.md](../IMPLEMENTATION_ROADMAP.md). The roadmap remains canonical.

| Phase | Objective | Status | Canonical Reference | Entry Gate | Exit Gate | Primary Artifacts |
| --- | --- | --- | --- | --- | --- | --- |
| Phase 1 | Establish Laravel package foundation and smoke-test command. | Implemented and verified | Roadmap Phase 1 | Empty or package-start repo. | Composer, provider, config publishing, command, Testbench smoke test green. | `composer.json`, config, service provider, `CheckCommand`, test harness. |
| Phase 2 | Extract destructive migration events into `SchemaChangeEvent[]`. | Implemented and verified | Roadmap Phase 2 | Phase 1 DoD green. | Parser/discovery/value-object tests and full suite green. | `MigrationDiscovery`, `MigrationParser`, value objects, migration fixtures. |
| Phase 3 | Build AST discovery and usage scanning. | Implemented and verified | Roadmap Phase 3 | Phase 2 DoD green. | AST scanner tests, parser regression green, type-change detection proven. | `ParsedFile`, `CodebaseIndexer`, `SchemaCallVisitor`, usage scanner. |
| Phase 4 | Convert changes and usages into graph/policy decisions. | Implemented and verified | Roadmap Phase 4 | Phase 3 usage model green. | Decision matrix, override, route, and graph tests green. | Graph, route bindings, policy engine, decision objects. |
| Phase 5 | Wire full CLI and analysis pipeline. | Implemented and verified | Roadmap Phase 5 | Phase 4 decision engine green. | End-to-end CLI tests green. | CLI options, pipeline orchestration, output rendering, JSON, exit codes. |
| Phase 6 | Add robustness, performance, and hardening. | Implemented and verified | Roadmap Phase 6 | Full Phase 5 pipeline green. | Raw SQL, migration robustness, relationship, cache, golden JSON, README, and coverage gates green. | Raw SQL visitor, AST cache, edge handling, expanded fixtures, golden JSON, README. |

## Completed Product Scope

Status: Implemented and verified.

- Laravel package foundation and publishable config.
- Migration discovery and AST migration parsing.
- AST source indexing and target-scoped usage discovery.
- Dependency graph, route bindings, and policy engine.
- CLI orchestration, console/JSON reporting, progress, Git diff discovery, and CI exit codes.
- Phase 6 hardening: raw SQL, neutralization, degradation honesty, complex relations, dynamic attributes, cache, golden JSON, README, and coverage.

## Next Safe Task

Release preparation, packaging validation, or explicitly scoped future-product planning.

Do not begin hosted PR checks, GitHub App work, SaaS/dashboard work, multi-repository orchestration, non-Laravel parsers, or ML calibration without a new explicit product scope.
