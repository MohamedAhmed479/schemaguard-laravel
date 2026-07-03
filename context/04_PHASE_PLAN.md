# Phase Plan

Read this for phase navigation or when deciding the next safe implementation task. Skip it for narrow parser bug fixes after phase scope is already clear.

This is not a duplicate of [../IMPLEMENTATION_ROADMAP.md](../IMPLEMENTATION_ROADMAP.md). The roadmap remains canonical.

| Phase | Objective | Status | Canonical Reference | Entry Gate | Exit Gate | Primary Artifacts |
| --- | --- | --- | --- | --- | --- | --- |
| Phase 1 | Establish Laravel package foundation and smoke-test command. | Implemented and verified | Roadmap Phase 1 | Empty or package-start repo. | Composer, provider, config publishing, command, Testbench smoke test green. | `composer.json`, `config/schemaguard.php`, service provider, `CheckCommand`, test harness. |
| Phase 2 | Extract destructive migration events into `SchemaChangeEvent[]`. | Implemented and verified | Roadmap Phase 2 | Phase 1 DoD green. | Parser/discovery/value-object tests and full suite green. | `MigrationDiscovery`, `MigrationParser`, value objects, migration fixtures. |
| Phase 3 | Build AST discovery and upgrade migration parsing where required. | Planned — not implemented | Roadmap Phase 3 | Phase 2 DoD green against current tree. | AST scanner tests, Phase 2 parser regression green, type-change detection proven. | See Phase 3A/3B/3C below. |
| Phase 4 | Convert changes and usages into graph/policy decisions. | Planned — not implemented | Roadmap Phase 4 | Phase 3 usage model green. | Decision matrix tests cover SAFE/WARNING/BLOCK. | Graph, policy engine, decision objects. |
| Phase 5 | Wire full CLI and analysis pipeline. | Planned — not implemented | Roadmap Phase 5 | Phase 4 decision engine green. | End-to-end CLI tests green. | CLI options, pipeline orchestration, output rendering. |
| Phase 6 | Add robustness, performance, and hardening. | Planned — not implemented | Roadmap Phase 6 | Full Phase 5 pipeline green. | Robustness/performance gates green. | Cache, edge handling, expanded fixtures. |

## Phase 3 Safe Milestones

### Phase 3A - AST Foundation

Status: Planned — not implemented.

Expected scope:

- `ParsedFile`
- `CodebaseIndexer`
- PHP-Parser integration
- `NameResolver`
- `ParentConnectingVisitor`
- Graceful broken-PHP handling

Do not include migration parser behavior changes unless required to prove the AST foundation can parse files.

### Phase 3B - AST Migration Parser Upgrade

Status: Planned — not implemented.

Expected scope:

- AST-backed `MigrationParser`
- Preserve `parseMany(array $paths): array` and `parseFile(string $path): array`
- `SchemaCallVisitor`
- Correct `up()` scope
- Custom Blueprint variable handling
- `COLUMN_TYPE_CHANGED` through `->change()`
- Phase 2 regression suite remains green

### Phase 3C - Usage Discovery Core

Status: Planned — not implemented.

Expected scope:

- `Confidence`
- `Usage`
- `ModelTableMap`
- `LocalTypeResolver`
- Usage visitors
- False-positive fixture gate

Do not begin Phase 3C until Phase 3A and 3B exits are green.
