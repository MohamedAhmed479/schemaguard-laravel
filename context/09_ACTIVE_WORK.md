# Active Work

Read this before taking ownership of a task and before handing work back. Do not skip it for implementation tasks.

Before taking ownership of a new task, update this file only after inspecting Git status and relevant context.

## Current Phase

Phase 3 preparation.

Status: Implemented and verified.

Phase 1 and Phase 2 are verified. Phase 3 is Planned — not implemented.

## Current Task

Agent Operating Context creation.

Status: Implemented and verified.

This documentation task passed `git diff --check` and the required validation commands listed in [03_CURRENT_STATE.md](03_CURRENT_STATE.md).

No Phase 3 implementation has started.

## Current Working Tree State

Needs verification at the start of every new task.

At the start of this context-system task, `git status --short` was clean and `git diff --check` passed. This task intentionally adds `AGENTS.md` and `context/`; until committed, those files are expected to appear in the working tree.

## Next Safe Task

Phase 3A — AST Foundation.

Entry gate:

- Re-run `git status`.
- Re-run `git diff --check`.
- Confirm Phase 1/2 tests are still green.
- Read [04_PHASE_PLAN.md](04_PHASE_PLAN.md), [05_CODEBASE_MAP.md](05_CODEBASE_MAP.md), and [07_TESTING_AND_COMMANDS.md](07_TESTING_AND_COMMANDS.md).

## Blocked By

No known project blocker.

Needs verification: current user intent and current Git state before beginning Phase 3A.

## Do Not Start Yet

Planned — not implemented until prior gates are green:

- Phase 3B
- Phase 3C
- Phase 4
- Phase 5
- Phase 6

## Handoff Notes

- Keep Phase 1/2 behavior stable while preparing Phase 3.
- Do not reintroduce `SchemaCallVisitor` before Phase 3B.
- Do not add `->change()` detection before Phase 3B.
- Keep documentation status labels truthful: implemented facts, planned work, and needs-verification items must stay separate.
