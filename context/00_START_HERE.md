# Start Here

Read this file when beginning any SchemaGuard task. Skip it only if you are already in the same active task and have just re-read the current Git state.

SchemaGuard is the `schemaguard/laravel` package: a deployment firewall for database schema changes in Laravel applications. Its job is to turn migration/schema evidence into a decision-oriented safety signal so teams can avoid destructive deployments that break live code.

## Current Phase

Status: Implemented and verified.

Phase 1, Phase 2, and Phase 3 are acceptance-verified against the current source and test suite. The next safe implementation task is Phase 4 - Graph + Policy, but only after re-verifying Git state and the Phase 1/2/3 gates.

Agents must not begin Phase N+1 until the preceding phase's Definition of Done is green.

## Minimum Bootstrap Read

For nearly every task, read only:

1. [03_CURRENT_STATE.md](03_CURRENT_STATE.md)
2. [06_ENGINEERING_RULES.md](06_ENGINEERING_RULES.md)
3. [09_ACTIVE_WORK.md](09_ACTIVE_WORK.md)

Then read task-specific context:

- Migration parsing: [02_ARCHITECTURE_AND_BOUNDARIES.md](02_ARCHITECTURE_AND_BOUNDARIES.md), [05_CODEBASE_MAP.md](05_CODEBASE_MAP.md), [07_TESTING_AND_COMMANDS.md](07_TESTING_AND_COMMANDS.md)
- Phase planning: [04_PHASE_PLAN.md](04_PHASE_PLAN.md), [08_DECISION_LOG.md](08_DECISION_LOG.md)
- Bug fix or regression: [03_CURRENT_STATE.md](03_CURRENT_STATE.md), [05_CODEBASE_MAP.md](05_CODEBASE_MAP.md), [07_TESTING_AND_COMMANDS.md](07_TESTING_AND_COMMANDS.md), [10_CHANGELOG.md](10_CHANGELOG.md)

## Mandatory Pre-Change Commands

Run these before changing code or context:

```bash
git status
git diff --check
```

Do not assume this context is current without checking Git state. If source code and `context/` disagree, current source code and tests win; update the context as part of the task.

## Source of Truth

1. Current source code and current tests
2. [../IMPLEMENTATION_ROADMAP.md](../IMPLEMENTATION_ROADMAP.md)
3. [../TECHNICAL_BLUEPRINT.md](../TECHNICAL_BLUEPRINT.md)
4. `context/` operational summaries
5. Git history and prior task reports

## Do Not Do This

- Do not implement Phase 4+ while fixing Phase 1/2/3 unless the task explicitly starts that phase.
- Do not add a policy engine, graph, full CLI pipeline, JSON output, Git diff support, route scanning, raw SQL visitor, or AST cache during Phase 3 maintenance.
- Do not execute host migrations or host models during analysis.
- Do not commit unless the user explicitly asks.
- Do not treat roadmap plans as implemented facts.

## Useful Links

- Roadmap: [../IMPLEMENTATION_ROADMAP.md](../IMPLEMENTATION_ROADMAP.md)
- Blueprint: [../TECHNICAL_BLUEPRINT.md](../TECHNICAL_BLUEPRINT.md)
- Current state: [03_CURRENT_STATE.md](03_CURRENT_STATE.md)
- Engineering rules: [06_ENGINEERING_RULES.md](06_ENGINEERING_RULES.md)
- Active work: [09_ACTIVE_WORK.md](09_ACTIVE_WORK.md)
- Testing commands: [07_TESTING_AND_COMMANDS.md](07_TESTING_AND_COMMANDS.md)

## Before You Start Any Task

- Run `git status` and `git diff --check`.
- Read the minimum bootstrap context.
- Read only the subsystem context needed for the task.
- Confirm the phase boundary and next safe task.
- Confirm whether the worktree has uncommitted user changes.
- Identify required targeted tests before editing.
- Keep code, tests, and context in agreement before finishing.
